<?php

declare(strict_types=1);

use PHPUnit\Framework\TestCase;

final class GreetingNameTest extends TestCase
{
	public static function setUpBeforeClass(): void
	{
		if (!defined('APP_FOLDER')) {
			define('APP_FOLDER', 'test');
		}
		require_once dirname(__DIR__) . '/app/config/function/coffeeji.php';
	}

	public function testArticleKeepsTheFullAccountName(): void
	{
		$this->assertSame('The Hendricks', hc_greeting_name('The Hendricks'));
		$this->assertSame('The Hendricks Coffee', hc_greeting_name('  The   Hendricks   Coffee  '));
		$this->assertSame('THE HENDRICKS', hc_greeting_name('THE HENDRICKS'));
		$this->assertSame('Der Hendricks', hc_greeting_name('Der Hendricks'));
	}

	public function testGivenNameStaysTheFirstWord(): void
	{
		$this->assertSame('Anna', hc_greeting_name('Anna Berger'));
		$this->assertSame('Theo', hc_greeting_name('Theo Hendricks'));
		$this->assertSame('The', hc_greeting_name('The'));
		$this->assertSame('', hc_greeting_name('   '));
	}

	public function testDashboardUsesTheGreetingName(): void
	{
		$source = (string) file_get_contents(dirname(__DIR__) . '/app/php/dashboard.php');
		$this->assertStringContainsString('hc_greeting_name(', $source);
		$this->assertStringNotContainsString('hc_first_name(', $source);
	}
}
