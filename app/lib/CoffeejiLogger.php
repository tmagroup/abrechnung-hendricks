<?php

declare(strict_types=1);

class CoffeejiLogger
{
	public function __construct(private string $file)
	{
	}

	public function info(string $message): void
	{
		$this->write('INFO', $message);
	}

	public function error(string $message): void
	{
		$this->write('ERROR', $message);
	}

	public static function redact(string $text): string
	{
		$text = preg_replace('/"devicePwd"\s*:\s*"(?:\\.|[^"\\\\])*"/', '"devicePwd":"[entfernt]"', $text) ?? $text;
		$text = preg_replace('/(key-md5\s*[:=]\s*)\S+/i', '$1[entfernt]', $text) ?? $text;
		$text = preg_replace('/(api[_-]?secret\s*[:=]\s*)\S+/i', '$1[entfernt]', $text) ?? $text;

		return $text;
	}

	private function write(string $level, string $message): void
	{
		$dir = dirname($this->file);
		if (!is_dir($dir)) {
			@mkdir($dir, 0775, true);
		}
		$line = '[' . date('Y-m-d H:i:s') . '] ' . $level . ' ' . self::redact($message) . PHP_EOL;
		@file_put_contents($this->file, $line, FILE_APPEND | LOCK_EX);
	}
}
