<?php

namespace OpenCart\CLI\Support;

use DOMDocument;

class ModificationRefresher
{
    /**
     * @var OpenCartRuntime
     */
    private $runtime;

    public function __construct(OpenCartRuntime $runtime)
    {
        $this->runtime = $runtime;
    }

    /**
     * @return array<string, int>
     */
    public function refresh(): array
    {
        $modificationDir = $this->runtime->getModificationDir();
        $this->clearModificationDirectory();

        $modificationModel = $this->runtime->model('setting/modification');
        $xmlSources = [];
        $systemModificationFile = $this->runtime->getSystemDir() . 'modification.xml';

        if (is_file($systemModificationFile)) {
            $xmlSources[] = (string) file_get_contents($systemModificationFile);
        }

        $systemOcmods = glob($this->runtime->getSystemDir() . '*.ocmod.xml') ?: [];
        foreach ($systemOcmods as $file) {
            $xmlSources[] = (string) file_get_contents($file);
        }

        foreach ($modificationModel->getModifications() as $modification) {
            if ((int) $modification['status'] === 1) {
                $xmlSources[] = (string) $modification['xml'];
            }
        }

        $modification = [];
        $original = [];
        $log = [];
        $processed = 0;

        foreach ($xmlSources as $xml) {
            if (trim($xml) === '') {
                continue;
            }

            $dom = new DOMDocument('1.0', 'UTF-8');
            $dom->preserveWhiteSpace = false;

            if (!$dom->loadXml($xml)) {
                continue;
            }

            $processed++;
            $nameNode = $dom->getElementsByTagName('name')->item(0);
            $log[] = 'MOD: ' . ($nameNode ? $nameNode->textContent : 'Unknown');

            $recovery = $modification;
            $fileNodes = $dom->getElementsByTagName('modification')->item(0)->getElementsByTagName('file');

            foreach ($fileNodes as $fileNode) {
                $operations = $fileNode->getElementsByTagName('operation');
                $patterns = explode('|', str_replace("\\", '/', $fileNode->getAttribute('path')));

                foreach ($patterns as $pattern) {
                    $sourcePattern = $this->resolveSourcePattern($pattern);
                    if ($sourcePattern === null) {
                        continue;
                    }

                    $matchedFiles = glob($sourcePattern, GLOB_BRACE) ?: [];
                    foreach ($matchedFiles as $matchedFile) {
                        $key = $this->createModificationKey($matchedFile);
                        if ($key === null) {
                            continue;
                        }

                        if (!isset($modification[$key])) {
                            $content = (string) file_get_contents($matchedFile);
                            $modification[$key] = preg_replace('~\r?\n~', "\n", $content);
                            $original[$key] = $modification[$key];
                        }

                        foreach ($operations as $operation) {
                            $result = $this->applyOperation($modification[$key], $operation, $log);

                            if (!$result['matched']) {
                                $errorMode = (string) $operation->getAttribute('error');
                                if ($errorMode === 'abort') {
                                    $modification = $recovery;
                                    break 4;
                                }

                                if ($errorMode !== 'skip') {
                                    break;
                                }
                            } else {
                                $modification[$key] = $result['content'];
                            }
                        }
                    }
                }
            }

            $log[] = '----------------------------------------------------------------';
        }

        $written = 0;
        foreach ($modification as $key => $value) {
            if (!isset($original[$key]) || $original[$key] === $value) {
                continue;
            }

            $targetPath = $modificationDir . $key;
            $targetDir = dirname($targetPath);
            if (!is_dir($targetDir)) {
                mkdir($targetDir, 0777, true);
            }

            file_put_contents($targetPath, $value);
            $written++;
        }

        $logFile = $this->runtime->getLogsDir() . 'ocmod.log';
        if (!is_dir(dirname($logFile))) {
            mkdir(dirname($logFile), 0777, true);
        }
        file_put_contents($logFile, implode("\n", $log) . "\n");

        return [
            'processed_modifications' => $processed,
            'written_files' => $written,
        ];
    }

    /**
     * @return int
     */
    public function clearModificationDirectory(): int
    {
        $files = [];
        $path = [$this->runtime->getModificationDir() . '*'];

        while (count($path) !== 0) {
            $next = array_shift($path);
            foreach (glob($next) ?: [] as $file) {
                if (is_dir($file)) {
                    $path[] = $file . '/*';
                }
                $files[] = $file;
            }
        }

        rsort($files);

        $deleted = 0;
        foreach ($files as $file) {
            if ($file === $this->runtime->getModificationDir() . 'index.html') {
                continue;
            }

            if (is_file($file)) {
                unlink($file);
                $deleted++;
            } elseif (is_dir($file)) {
                rmdir($file);
            }
        }

        return $deleted;
    }

    private function resolveSourcePattern(string $pattern): ?string
    {
        $pattern = ltrim($pattern, '/');

        if (strpos($pattern, 'catalog/') === 0) {
            return $this->runtime->getOpenCartRoot() . '/catalog/' . substr($pattern, 8);
        }

        if (strpos($pattern, 'admin/') === 0) {
            return $this->runtime->getOpenCartRoot() . '/admin/' . substr($pattern, 6);
        }

        if (strpos($pattern, 'system/') === 0) {
            return $this->runtime->getSystemDir() . substr($pattern, 7);
        }

        return null;
    }

    private function createModificationKey(string $file): ?string
    {
        $systemDir = $this->runtime->getSystemDir();
        $catalogDir = $this->runtime->getOpenCartRoot() . '/catalog/';
        $adminDir = $this->runtime->getOpenCartRoot() . '/admin/';

        if (strpos($file, $catalogDir) === 0) {
            return 'catalog/' . substr($file, strlen($catalogDir));
        }

        if (strpos($file, $adminDir) === 0) {
            return 'admin/' . substr($file, strlen($adminDir));
        }

        if (strpos($file, $systemDir) === 0) {
            return 'system/' . substr($file, strlen($systemDir));
        }

        return null;
    }

    /**
     * @return array{matched: bool, content: string}
     */
    private function applyOperation(string $content, \DOMElement $operation, array &$log): array
    {
        $ignoreIf = $operation->getElementsByTagName('ignoreif')->item(0);
        if ($ignoreIf) {
            if ($ignoreIf->getAttribute('regex') !== 'true' && strpos($content, $ignoreIf->textContent) !== false) {
                return ['matched' => true, 'content' => $content];
            }

            if ($ignoreIf->getAttribute('regex') === 'true' && preg_match($ignoreIf->textContent, $content)) {
                return ['matched' => true, 'content' => $content];
            }
        }

        $searchNode = $operation->getElementsByTagName('search')->item(0);
        $addNode = $operation->getElementsByTagName('add')->item(0);

        if (!$searchNode || !$addNode) {
            return ['matched' => false, 'content' => $content];
        }

        if ($searchNode->getAttribute('regex') === 'true') {
            $search = trim($searchNode->textContent);
            $replace = trim($addNode->textContent);
            $limit = $searchNode->getAttribute('limit');
            $limitValue = $limit === '' ? -1 : (int) $limit;
            $matched = preg_match($search, $content) === 1;

            if ($matched) {
                $log[] = 'REGEX: ' . $search;
                $content = preg_replace($search, $replace, $content, $limitValue);
            }

            return ['matched' => $matched, 'content' => $content];
        }

        $search = $searchNode->textContent;
        $trimSearch = $searchNode->getAttribute('trim');
        if ($trimSearch === '' || $trimSearch === 'true') {
            $search = trim($search);
        }

        $add = $addNode->textContent;
        if ($addNode->getAttribute('trim') === 'true') {
            $add = trim($add);
        }

        $position = $addNode->getAttribute('position') ?: 'replace';
        $offset = $addNode->getAttribute('offset') === '' ? 0 : (int) $addNode->getAttribute('offset');
        $index = $searchNode->getAttribute('index');
        $indexes = $index !== '' ? array_map('intval', explode(',', $index)) : [];

        $lines = explode("\n", $content);
        $matched = false;
        $occurrence = 0;

        for ($lineId = 0; $lineId < count($lines); $lineId++) {
            $line = $lines[$lineId];
            $isLineMatch = false;

            if (stripos($line, $search) !== false) {
                if (!$indexes || in_array($occurrence, $indexes, true)) {
                    $isLineMatch = true;
                }
                $occurrence++;
            }

            if (!$isLineMatch) {
                continue;
            }

            $matched = true;
            $newLines = explode("\n", $add);

            switch ($position) {
                case 'before':
                    array_splice($lines, max(0, $lineId - $offset), 0, $newLines);
                    $lineId += count($newLines);
                    break;
                case 'after':
                    array_splice($lines, ($lineId + 1) + $offset, 0, $newLines);
                    $lineId += count($newLines);
                    break;
                case 'replace':
                default:
                    $replacement = [str_replace($search, $add, $line)];
                    if ($offset < 0) {
                        array_splice($lines, $lineId + $offset, abs($offset) + 1, $replacement);
                        $lineId -= $offset;
                    } else {
                        array_splice($lines, $lineId, $offset + 1, $replacement);
                    }
                    break;
            }
        }

        return ['matched' => $matched, 'content' => implode("\n", $lines)];
    }
}
