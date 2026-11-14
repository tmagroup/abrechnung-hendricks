<?php

declare(strict_types=1);

use PHPUnit\Framework\Attributes\PreserveGlobalState;
use PHPUnit\Framework\Attributes\RunInSeparateProcess;
use PHPUnit\Framework\TestCase;

final class CoffeejiWebhookTest extends TestCase
{
	private const TOKEN = '0123456789abcdef0123456789abcdef';

	private string $logFile;

	protected function setUp(): void
	{
		$this->logFile = sys_get_temp_dir() . '/cj-webhook-' . bin2hex(random_bytes(4)) . '.log';
	}

	protected function tearDown(): void
	{
		if (is_file($this->logFile)) {
			unlink($this->logFile);
		}
	}

	public function testEndpointStaysClosedWithoutToken(): void
	{
		$result = $this->webhook('')->handle('POST', '{}', array('token' => ''), $this->server());

		$this->assertSame(403, $result->status);
		$this->assertNotSame(CoffeejiWebhook::SUCCESS_BODY, $result->body);
	}

	public function testShortOrPlaceholderTokenDisablesTheEndpoint(): void
	{
		foreach (array('kurz', 'CHANGE_ME', 'change_me') as $token) {
			$result = $this->webhook($token)->handle('POST', '{}', array('token' => $token), $this->server());
			$this->assertSame(403, $result->status, $token);
		}
	}

	public function testWrongOrMissingTokenIsForbiddenAndWritesNothing(): void
	{
		$wrong = $this->webhook(self::TOKEN)->handle('POST', $this->json($this->fault('1')), array('token' => 'ffffffffffffffffffffffffffffffff'), $this->server());
		$missing = $this->webhook(self::TOKEN)->handle('POST', $this->json($this->fault('1')), array(), $this->server());

		$this->assertSame(403, $wrong->status);
		$this->assertSame(403, $missing->status);
		$this->assertSame($wrong->body, $missing->body);
		$this->assertStringNotContainsString('Success', $wrong->body);
		$this->assertStringNotContainsString(self::TOKEN, $wrong->body);
	}

	public function testForeignIpIsForbidden(): void
	{
		$result = $this->webhook(self::TOKEN, array('203.0.113.10'))->handle(
			'POST',
			$this->json($this->fault('1')),
			array('token' => self::TOKEN),
			$this->server('198.51.100.5')
		);

		$this->assertSame(403, $result->status);
	}

	public function testAllowedIpStillRejectsGet(): void
	{
		$result = $this->webhook(self::TOKEN, array('203.0.113.10'))->handle(
			'GET',
			'',
			array('token' => self::TOKEN),
			$this->server('203.0.113.10')
		);

		$this->assertSame(405, $result->status);
		$this->assertNotSame(CoffeejiWebhook::SUCCESS_BODY, $result->body);
	}

	public function testOversizedBodyIsRejectedBeforeProcessing(): void
	{
		$body = '{"deviceRunStatus":2,"exceptionRemark":"x"}';
		$result = $this->webhook(self::TOKEN, array(), 1024)->handle(
			'POST',
			$body,
			array('token' => self::TOKEN),
			array('REMOTE_ADDR' => '127.0.0.1', 'CONTENT_LENGTH' => 5000)
		);

		$this->assertSame(413, $result->status);
	}

	public function testSuccessBodyHasTheDocumentedShape(): void
	{
		$parsed = json_decode(CoffeejiWebhook::SUCCESS_BODY, true);

		$this->assertSame(
			array('code' => 200, 'success' => true, 'data' => true, 'msg' => 'Success'),
			$parsed
		);
		$this->assertSame(CoffeejiWebhook::SUCCESS_BODY, json_encode($parsed));
	}

	public function testDetectsAllThreeEventTypesAndPrefersOrders(): void
	{
		$this->assertSame('device_fault', CoffeejiWebhook::detectType(array(
			'deviceRunStatus' => 2,
			'exceptionRemark' => 'E0',
		)));
		$this->assertSame('material_alarm', CoffeejiWebhook::detectType(array(
			'deviceScarceStatus' => 3,
			'scarceRemark' => 'Mangel',
		)));
		$this->assertSame('order', CoffeejiWebhook::detectType(array(
			'orderNo' => 'A-1',
			'orderStatus' => 2,
			'payAmount' => 1,
			'deviceRunStatus' => 2,
			'exceptionRemark' => 'soll die Bestellung nicht überdecken',
		)));
		$this->assertNull(CoffeejiWebhook::detectType(array('deviceId' => '1')));
	}

	public function testWebhookStatusSevenIsRefundNotTheListMeaning(): void
	{
		$mapped = CoffeejiWebhook::mapOrderStatus(7);

		$this->assertSame(0, $mapped['status']);
		$this->assertSame(1, $mapped['refund_status']);
		$this->assertNotSame(7, $mapped['status']);
		$this->assertSame(2, CoffeejiWebhook::mapOrderStatus(2)['status']);
		$this->assertNull(CoffeejiWebhook::mapOrderStatus(2)['refund_status']);
		$this->assertSame(1, CoffeejiWebhook::mapOrderStatus(1)['status']);
		$this->assertSame(6, CoffeejiWebhook::mapOrderStatus(6)['status']);
		$this->assertSame(0, CoffeejiWebhook::mapOrderStatus(0)['status']);
	}

	public function testDevicePasswordIsRemovedBeforeHashing(): void
	{
		$plain = array('deviceRunStatus' => 2, 'exceptionRemark' => 'E0', 'deviceId' => '9000000000000000201');
		$withSecret = $plain;
		$withSecret['devicePwd'] = 'darf-nicht-gespeichert-werden';
		$withSecret['nested'] = array('keep' => 'ja', 'devicePwd' => 'auch-nicht');
		$clean = CoffeejiWebhook::clean($withSecret);

		$this->assertArrayNotHasKey('devicePwd', $clean);
		$this->assertSame('ja', $clean['nested']['keep']);
		$this->assertArrayNotHasKey('devicePwd', $clean['nested']);
		$this->assertFalse(CoffeejiResponse::containsSecret($clean));
		$this->assertSame(
			CoffeejiWebhook::hashPayload('device_fault', CoffeejiWebhook::clean($plain)),
			CoffeejiWebhook::hashPayload('device_fault', CoffeejiWebhook::clean($plain + array('devicePwd' => 'darf-nicht-gespeichert-werden')))
		);
	}

	public function testWebhookTimeIsStoredForDisplayInBerlin(): void
	{
		$this->assertSame('2023-11-14 23:13:20', CoffeejiTime::storeWebhook(1700000000000));
		$this->assertSame('2026-11-14 08:00:00', CoffeejiTime::storeWebhook('2026-11-14 15:00:00'));
		$this->assertSame('2026-11-14 22:00:00', CoffeejiTime::utcStoredToLocal('2026-11-14 21:00:00'));
	}

	public function testPickWebhookHintOnlyForTheMatchingAlarm(): void
	{
		$alerts = array(
			'device_fault' => '2026-11-14 22:10:00',
			'material_alarm' => '2026-11-14 22:12:00',
		);

		$this->assertSame('2026-11-14 22:10:00', CoffeejiReadModel::pickWebhookAt(array(
			'device_run_status' => 2,
			'scarce_status' => 3,
		), $alerts));
		$this->assertSame('2026-11-14 22:12:00', CoffeejiReadModel::pickWebhookAt(array(
			'device_run_status' => 1,
			'scarce_status' => 3,
		), $alerts));
		$this->assertNull(CoffeejiReadModel::pickWebhookAt(array(
			'device_run_status' => 1,
			'scarce_status' => 1,
		), $alerts));
	}

	public function testLoggerDropsTokenAndDevicePassword(): void
	{
		$line = CoffeejiLogger::redact('?token=' . self::TOKEN . ' {"devicePwd":"darf-nicht-gespeichert-werden"} api_secret=xyz');

		$this->assertStringNotContainsString(self::TOKEN, $line);
		$this->assertStringNotContainsString('darf-nicht-gespeichert-werden', $line);
		$this->assertStringNotContainsString('xyz', $line);
	}

	public function testReceiverDoesNotCallTheCoffeejiApi(): void
	{
		$source = (string) file_get_contents(dirname(__DIR__) . '/app/lib/CoffeejiWebhook.php');
		$front = (string) file_get_contents(dirname(__DIR__) . '/coffeeji_webhook.php');

		$this->assertStringNotContainsString('CoffeejiClient', $source);
		$this->assertStringNotContainsString('produceCoffee', $source);
		$this->assertStringNotContainsString('replenish', $source);
		$this->assertStringNotContainsString('afterLogin', $front);
		$this->assertFileDoesNotExist(dirname(__DIR__) . '/app/php/coffeeji_webhook.php');
	}

	public function testPublicRouteBypassesTheLoginSession(): void
	{
		$settings = (string) file_get_contents(dirname(__DIR__) . '/app/config/settings.php');
		$htaccess = (string) file_get_contents(dirname(__DIR__) . '/.htaccess');

		$this->assertLessThan(strpos($settings, 'session_start'), strpos($settings, 'coffeeji_webhook_dispatch()'));
		$this->assertLessThan(strpos($htaccess, 'index.php/$1'), strpos($htaccess, 'coffeeji_webhook.php'));
	}

	public function testInvalidIpListFailsClosed(): void
	{
		$this->assertSame(array(), CoffeejiWebhook::parseIpList(''));
		$this->assertSame(array('203.0.113.10', '2001:db8::1'), CoffeejiWebhook::parseIpList('203.0.113.10, 2001:db8::1'));
		$this->assertSame(array('*'), CoffeejiWebhook::parseIpList('keine-ip'));
	}

	#[RunInSeparateProcess]
	#[PreserveGlobalState(false)]
	public function testMachineCardShowsLastWebhookOnFaultOnly(): void
	{
		if (!defined('APP_FOLDER')) {
			define('APP_FOLDER', 'app');
		}
		require dirname(__DIR__) . '/app/config/function/coffeeji.php';

		$html = hc_machine_card(array(
			'name' => 'Demo',
			'status' => 1,
			'device_ext_no' => 'DEMO',
			'device_run_status' => 2,
			'exception_remark' => 'E0: Becherspender',
			'scarce_status' => 1,
			'webhook_at' => '2026-11-14 22:10:00',
			'materials' => array(),
			'month_amount' => 0,
			'month_count' => 0,
			'last_sale' => null,
		));
		$this->assertStringContainsString('Letzter Webhook', $html);
		$this->assertStringContainsString('14.11.2026, 22:10', $html);
		$this->assertStringContainsString('Becherspender', $html);

		$quiet = hc_machine_card(array(
			'name' => 'Ruhig',
			'status' => 1,
			'device_run_status' => 1,
			'exception_remark' => '',
			'scarce_status' => 1,
			'webhook_at' => '2026-11-14 22:10:00',
			'materials' => array(),
		));
		$this->assertStringNotContainsString('Letzter Webhook', $quiet);
	}

	/**
	 * @param list<string> $ips
	 */
	private function webhook(string $token, array $ips = array(), int $max = 65536): CoffeejiWebhook
	{
		return new CoffeejiWebhook(
			new CoffeejiWebhookConfig($token, $ips, $max),
			new CoffeejiWebhookGuardRepository(),
			new CoffeejiLogger($this->logFile)
		);
	}

	/**
	 * @param array<string, mixed> $payload
	 */
	private function json(array $payload): string
	{
		$json = json_encode($payload, JSON_UNESCAPED_UNICODE);
		$this->assertNotFalse($json);

		return $json;
	}

	/**
	 * @return array<string, mixed>
	 */
	private function fault(string $id): array
	{
		return array(
			'deviceId' => $id,
			'deviceRunStatus' => 2,
			'exceptionRemark' => 'E0',
		);
	}

	/**
	 * @return array<string, mixed>
	 */
	private function server(string $ip = '127.0.0.1'): array
	{
		return array('REMOTE_ADDR' => $ip, 'CONTENT_LENGTH' => 20);
	}
}

class CoffeejiWebhookGuardRepository extends CoffeejiRepository
{
	public function insertWebhookEvent(
		string $eventType,
		string $deviceId,
		string $deviceNo,
		string $orderNo,
		string $payloadJson,
		string $payloadHash,
		string $processedStatus,
		?string $errorMessage
	): int {
		throw new RuntimeException('Der abgelehnte Aufruf darf nichts speichern.');
	}
}
