<?php

declare(strict_types=1);

class CoffeejiSync
{
	public function __construct(
		private CoffeejiClient $client,
		private CoffeejiRepository $repository
	) {
	}

	/**
	 * @return array{records: int, message: string}
	 */
	public function devices(): array
	{
		$records = $this->collectPages(function (int $page): array {
			return $this->client->devicePage($page, 100);
		});
		$devices = array();
		foreach ($records as $row) {
			$device = CoffeejiDevice::normalize($row);
			if ($device['id'] !== '') {
				$devices[] = $device;
			}
		}
		$count = $this->repository->upsertDevices($devices);

		return array('records' => $count, 'message' => $count . ' Geräte übernommen.');
	}

	/**
	 * @return array{records: int, message: string}
	 */
	public function materials(): array
	{
		$devices = $this->repository->devices();
		$total = 0;
		foreach ($devices as $device) {
			$id = (string) $device['id'];
			if (preg_match('/^[0-9]{1,32}$/', $id) !== 1) {
				continue;
			}
			$parsed = $this->client->material($id);
			$rows = CoffeejiResponse::records($parsed);
			if ($rows === array() && isset($parsed['data']) && is_array($parsed['data']) && !isset($parsed['data']['records'])) {
				if (array_is_list($parsed['data'])) {
					$rows = $parsed['data'];
				} elseif (isset($parsed['data']['list']) && is_array($parsed['data']['list'])) {
					$rows = $parsed['data']['list'];
				}
			}
			$materials = array();
			foreach ($rows as $row) {
				if (is_array($row)) {
					$materials[] = CoffeejiMaterial::normalize($row);
				}
			}
			$total += $this->repository->upsertMaterials($id, $materials);
		}

		return array('records' => $total, 'message' => $total . ' Füllstände übernommen.');
	}

	/**
	 * @return array{records: int, message: string}
	 */
	public function orders(DateTimeImmutable $from, DateTimeImmutable $to): array
	{
		$endDate = $to->modify('-1 second');
		$records = $this->collectPages(function (int $page) use ($from, $endDate): array {
			return $this->client->orderList($page, 100, $from->format('Y-m-d'), $endDate->format('Y-m-d'));
		});
		$orders = array();
		foreach ($records as $row) {
			$order = CoffeejiOrder::normalize($row);
			if ($order['order_no'] !== '') {
				$orders[] = $order;
			}
		}
		$orders = CoffeejiOrder::filterWindow($orders, $from, $to);
		$result = $this->repository->upsertOrders($orders);
		$message = $result['insert'] . ' neu, ' . $result['update'] . ' aktualisiert, ' . $result['unchanged'] . ' unverändert.';

		return array(
			'records' => $result['insert'] + $result['update'],
			'message' => $message,
		);
	}

	/**
	 * @param callable(int): array<string, mixed> $fetch
	 * @return list<array<string, mixed>>
	 */
	private function collectPages(callable $fetch): array
	{
		$page = 1;
		$all = array();
		do {
			$parsed = $fetch($page);
			$records = CoffeejiResponse::records($parsed);
			foreach ($records as $row) {
				$all[] = $row;
			}
			$pages = CoffeejiResponse::pages($parsed);
			if ($records === array() || count($records) < 100) {
				break;
			}
			$page++;
		} while ($page <= $pages && $page <= 50);

		return $all;
	}
}
