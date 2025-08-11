<?php

/**
 * OC-CLI - OpenCart Command Line Interface
 *
 * @author    Custom Services Limited <info@opencartgreece.gr>
 * @copyright 2024 Custom Services Limited
 * @license   GPL-3.0-or-later
 * @link      https://support.opencartgreece.gr/
 * @link      https://github.com/Custom-Services-Limited/oc-cli
 */

namespace OpenCart\CLI\Commands\Extension;

use OpenCart\CLI\Command;
use Symfony\Component\Console\Input\InputArgument;
use Symfony\Component\Console\Input\InputOption;

class InstallCommand extends Command
{
    protected function configure()
    {
        parent::configure();

        $this
            ->setName('extension:install')
            ->setDescription('Install an extension')
            ->addArgument(
                'extension',
                InputArgument::REQUIRED,
                'Extension file path or identifier'
            )
            ->addOption(
                'activate',
                'a',
                InputOption::VALUE_NONE,
                'Activate extension after installation'
            );
    }

    protected function handle()
    {
        if (!$this->requireOpenCart()) {
            return 1;
        }

        $connection = $this->getDatabaseConnection();
        if (!$connection) {
            $this->io->error('Could not connect to database.');
            return 1;
        }

        $extensionPath = $this->input->getArgument('extension');
        $activate = $this->input->getOption('activate');

        // Check OpenCart version for OCMOD compatibility
        $version = $this->getOpenCartVersion();
        if ($this->isOpenCart4($version)) {
            $this->io->warning('Extension installation is not fully supported for OpenCart 4.');
            $this->io->text('This feature is designed for OpenCart 3 with OCMOD support.');

            if (!$this->io->confirm('Continue anyway?', false)) {
                $connection->close();
                return 0;
            }
        }

        // Validate extension file
        if (!$this->validateExtensionFile($extensionPath)) {
            $connection->close();
            return 1;
        }

        $this->io->title('Installing Extension');
        $this->io->text("Extension: {$extensionPath}");

        try {
            $extensionData = $this->extractExtensionData($extensionPath);
            $installId = $this->installExtension($connection, $extensionData);

            if ($installId) {
                $this->io->success("Extension '{$extensionData['name']}' installed successfully.");

                if ($activate) {
                    $this->io->text('Activating extension...');
                    if ($this->activateExtension($connection, $installId, $extensionData)) {
                        $this->io->success('Extension activated successfully.');
                    } else {
                        $this->io->warning('Extension installed but activation failed.');
                    }
                }
            } else {
                $this->io->error('Extension installation failed.');
                $connection->close();
                return 1;
            }
        } catch (\Exception $e) {
            $this->io->error("Installation failed: " . $e->getMessage());
            $connection->close();
            return 1;
        }

        $connection->close();
        return 0;
    }

    private function validateExtensionFile($path)
    {
        if (!file_exists($path)) {
            $this->io->error("Extension file not found: {$path}");
            return false;
        }

        if (!is_readable($path)) {
            $this->io->error("Extension file is not readable: {$path}");
            return false;
        }

        $allowedExtensions = ['zip', 'ocmod', 'xml'];
        $extension = strtolower(pathinfo($path, PATHINFO_EXTENSION));

        if (!in_array($extension, $allowedExtensions)) {
            $this->io->error("Unsupported extension file type. Allowed: " . implode(', ', $allowedExtensions));
            return false;
        }

        return true;
    }

    private function extractExtensionData($path)
    {
        $filename = basename($path);
        $extension = strtolower(pathinfo($path, PATHINFO_EXTENSION));

        // Basic extension data - in a real implementation, this would parse
        // the extension file to extract metadata
        $data = [
            'name' => pathinfo($filename, PATHINFO_FILENAME),
            'code' => preg_replace('/[^a-z0-9_]/', '_', strtolower(pathinfo($filename, PATHINFO_FILENAME))),
            'type' => 'module', // Default type
            'version' => '1.0.0',
            'author' => 'Unknown',
            'filename' => $filename,
            'path' => $path
        ];

        // For OCMOD files, try to extract XML metadata
        if ($extension === 'xml' || $extension === 'ocmod') {
            $xmlData = $this->parseOcmodXml($path);
            if ($xmlData) {
                $data = array_merge($data, $xmlData);
            }
        }

        return $data;
    }

    private function parseOcmodXml($path)
    {
        try {
            $xml = simplexml_load_file($path);
            if ($xml === false) {
                return null;
            }

            return [
                'name' => (string)$xml->name ?: 'Unknown Extension',
                'code' => (string)$xml->code ?: preg_replace('/[^a-z0-9_]/', '_', strtolower((string)$xml->name)),
                'version' => (string)$xml->version ?: '1.0.0',
                'author' => (string)$xml->author ?: 'Unknown'
            ];
        } catch (\Exception $e) {
            $this->io->warning("Could not parse OCMOD XML: " . $e->getMessage());
            return null;
        }
    }

    private function installExtension($connection, $data)
    {
        $config = $this->getOpenCartConfig();
        $prefix = $config['db_prefix'];

        // Check if extension is already installed
        $checkSql = "SELECT extension_install_id FROM {$prefix}extension_install WHERE code = ?";
        $stmt = $connection->prepare($checkSql);
        $stmt->bind_param('s', $data['code']);
        $stmt->execute();
        $result = $stmt->get_result();

        if ($result->fetch_assoc()) {
            throw new \Exception("Extension '{$data['code']}' is already installed.");
        }

        // Insert into extension_install table
        $sql = "
            INSERT INTO {$prefix}extension_install 
            (type, code, name, version, author, filename, date_added) 
            VALUES (?, ?, ?, ?, ?, ?, NOW())
        ";

        $stmt = $connection->prepare($sql);
        $stmt->bind_param(
            'ssssss',
            $data['type'],
            $data['code'],
            $data['name'],
            $data['version'],
            $data['author'],
            $data['filename']
        );

        if ($stmt->execute()) {
            $installId = $connection->insert_id;

            // Add to extension_path table if applicable
            $this->addExtensionPath($connection, $installId, $data);

            return $installId;
        }

        return false;
    }

    private function addExtensionPath($connection, $installId, $data)
    {
        $config = $this->getOpenCartConfig();
        $prefix = $config['db_prefix'];

        $sql = "INSERT INTO {$prefix}extension_path (extension_install_id, path) VALUES (?, ?)";
        $stmt = $connection->prepare($sql);
        $stmt->bind_param('is', $installId, $data['path']);
        $stmt->execute();
    }

    private function activateExtension($connection, $installId, $data)
    {
        $config = $this->getOpenCartConfig();
        $prefix = $config['db_prefix'];

        // Add to extension table to activate
        $sql = "INSERT INTO {$prefix}extension (extension_install_id, type, code) VALUES (?, ?, ?)";
        $stmt = $connection->prepare($sql);
        $stmt->bind_param('iss', $installId, $data['type'], $data['code']);

        return $stmt->execute();
    }

    private function getOpenCartVersion()
    {
        if (!$this->openCartRoot) {
            return null;
        }

        // Try to get version from various locations
        $versionFiles = [
            $this->openCartRoot . '/system/startup.php',
            $this->openCartRoot . '/admin/model/setting/setting.php',
            $this->openCartRoot . '/index.php'
        ];

        foreach ($versionFiles as $file) {
            if (file_exists($file)) {
                $content = file_get_contents($file);
                if (preg_match("/VERSION['\"]?\s*[=:]\s*['\"]([0-9\.]+)/i", $content, $matches)) {
                    return $matches[1];
                }
            }
        }

        return null;
    }

    private function isOpenCart4($version)
    {
        return $version && version_compare($version, '4.0.0', '>=');
    }
}
