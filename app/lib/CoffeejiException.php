<?php

declare(strict_types=1);

class CoffeejiException extends RuntimeException
{
	public function __construct(
		string $message,
		private int $apiCode = 0
	) {
		parent::__construct($message);
	}

	public function apiCode(): int
	{
		return $this->apiCode;
	}
}
