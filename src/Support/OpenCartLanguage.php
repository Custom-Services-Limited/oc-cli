<?php

namespace OpenCart\CLI\Support;

class OpenCartLanguage
{
    /**
     * @var string
     */
    private $default = 'en-gb';

    /**
     * @var string
     */
    private $directory;

    /**
     * @var string
     */
    private $languageRoot;

    /**
     * @var array<string, mixed>
     */
    public $data = [];

    public function __construct(string $languageRoot, string $directory = 'en-gb')
    {
        $this->languageRoot = rtrim($languageRoot, '/\\');
        $this->directory = $directory ?: $this->default;
    }

    /**
     * @return mixed
     */
    public function get(string $key)
    {
        return array_key_exists($key, $this->data) ? $this->data[$key] : $key;
    }

    /**
     * @param mixed $value
     */
    public function set(string $key, $value): void
    {
        $this->data[$key] = $value;
    }

    /**
     * @return array<string, mixed>
     */
    public function all(): array
    {
        return $this->data;
    }

    /**
     * @return array<string, mixed>
     */
    public function load(string $filename, string $key = ''): array
    {
        if ($key !== '') {
            $nested = new self($this->languageRoot, $this->directory);
            $nested->load($filename);
            $this->data[$key] = $nested;

            return $this->data;
        }

        $translations = [];

        $defaultFile = $this->languageRoot . '/' . $this->default . '/' . $filename . '.php';
        if (is_file($defaultFile)) {
            $_ = [];
            require $defaultFile;
            $translations = array_merge($translations, $_);
        }

        $languageFile = $this->languageRoot . '/' . $this->directory . '/' . $filename . '.php';
        if (is_file($languageFile)) {
            $_ = [];
            require $languageFile;
            $translations = array_merge($translations, $_);
        }

        $this->data = array_merge($this->data, $translations);

        return $this->data;
    }
}
