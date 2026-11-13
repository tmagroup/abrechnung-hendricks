<?php

declare(strict_types=1);

class CoffeejiReadModel
{
	public function __construct(private CoffeejiRepository $repository)
	{
	}

	/**
	 * @return array{state: string, message: string}
	 */
	public function readiness(): array
	{
		$configured = function_exists('coffeeji_configured') ? coffeeji_configured() : false;
		$tables = false;
		$dbError = false;
		try {
			$tables = $this->repository->tablesReady();
		} catch (Throwable $e) {
			$dbError = true;
		}

		if (!$configured && (!$tables || $dbError)) {
			return array(
				'state' => 'setup',
				'message' => 'Die Live-Übersicht ist noch nicht eingerichtet. Tragen Sie die Coffeeji-Schlüssel in der Konfiguration ein und spielen Sie die Datenbank-Migration ein. Die Abrechnung läuft davon unabhängig weiter.',
			);
		}
		if (!$configured) {
			return array(
				'state' => 'unconfigured',
				'message' => 'Die Coffeeji-Schlüssel fehlen noch. Sobald COFFEEJI_API_KEY und COFFEEJI_API_SECRET gesetzt sind, kann der Abruf starten. Die Abrechnung bleibt nutzbar.',
			);
		}
		if ($dbError || !$tables) {
			return array(
				'state' => 'nomigration',
				'message' => 'Die Coffeeji-Tabellen fehlen noch. Bitte die Migration app/db/migrations/20261113_coffeeji.sql einspielen. Bis dahin bleiben die bestehenden Abrechnungsseiten unverändert nutzbar.',
			);
		}

		return array('state' => 'ready', 'message' => '');
	}

	/**
	 * @return array<string, mixed>
	 */
	public function dashboard(DateTimeImmutable $now): array
	{
		$ready = $this->readiness();
		if ($ready['state'] !== 'ready') {
			return $ready;
		}

		$zoneNow = $now->setTimezone(CoffeejiTime::zone());
		$monthStart = $zoneNow->modify('first day of this month')->setTime(0, 0);
		$prevMonthStart = $monthStart->modify('-1 month');
		$chartStart = $zoneNow->setTime(0, 0)->modify('-29 days');
		$from = $prevMonthStart < $chartStart ? $prevMonthStart : $chartStart;
		$to = $zoneNow->setTime(0, 0)->modify('+1 day');
		$orders = $this->repository->ordersBetween($from, $to);
		$summary = CoffeejiRevenue::summarize($orders, $zoneNow);
		$devices = $this->decorateDevices($orders, $monthStart, $to);
		$recent = array();
		foreach ($orders as $order) {
			if (CoffeejiOrder::isSale($order)) {
				$recent[] = $order;
			}
			if (count($recent) >= 8) {
				break;
			}
		}

		return array(
			'state' => 'ready',
			'message' => '',
			'summary' => $summary,
			'devices' => $devices,
			'recent' => $recent,
			'last_orders_sync' => $this->repository->lastSuccess('orders'),
		);
	}

	/**
	 * @return array<string, mixed>
	 */
	public function machines(): array
	{
		$ready = $this->readiness();
		if ($ready['state'] !== 'ready') {
			return $ready;
		}
		$now = CoffeejiTime::now();
		$from = $now->modify('first day of this month')->setTime(0, 0);
		$to = $now->setTime(0, 0)->modify('+1 day');
		$orders = $this->repository->ordersBetween($from, $to);

		return array(
			'state' => 'ready',
			'message' => '',
			'devices' => $this->decorateDevices($orders, $from, $to),
		);
	}

	/**
	 * @return array<string, mixed>
	 */
	public function live(DateTimeImmutable $day, ?string $deviceId): array
	{
		$ready = $this->readiness();
		if ($ready['state'] !== 'ready') {
			return $ready;
		}
		$from = $day->setTimezone(CoffeejiTime::zone())->setTime(0, 0);
		$to = $from->modify('+1 day');
		$orders = $this->repository->ordersBetween($from, $to);
		if ($deviceId !== null && $deviceId !== '') {
			$orders = array_values(array_filter($orders, function (array $order) use ($deviceId): bool {
				return (string) ($order['device_id'] ?? '') === $deviceId
					|| (string) ($order['device_ext_no'] ?? '') === $deviceId;
			}));
		}
		$sales = CoffeejiRevenue::sum($orders, $from, $to);

		return array(
			'state' => 'ready',
			'message' => '',
			'orders' => array_slice($orders, 0, 500),
			'sales' => $sales,
			'truncated' => count($orders) > 500,
		);
	}

	/**
	 * @param list<array<string, mixed>> $orders
	 * @return list<array<string, mixed>>
	 */
	private function decorateDevices(array $orders, DateTimeImmutable $from, DateTimeImmutable $to): array
	{
		$devices = $this->repository->devices();
		$materials = $this->repository->materialsGrouped();
		$stats = CoffeejiRevenue::perDevice($orders, $from, $to);
		$out = array();
		foreach ($devices as $device) {
			$id = (string) $device['id'];
			$ext = (string) ($device['device_ext_no'] ?? '');
			$stat = $stats[$id] ?? $stats[$ext] ?? array('amount' => 0.0, 'count' => 0, 'last' => null);
			$device['materials'] = $materials[$id] ?? array();
			$device['month_amount'] = $stat['amount'];
			$device['month_count'] = $stat['count'];
			$device['last_sale'] = $stat['last'];
			$out[] = $device;
		}

		return $out;
	}
}
