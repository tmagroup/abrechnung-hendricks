<?php

declare(strict_types=1);

class AbrechnungListQuery
{
	/** @var list<string> */
	public const COLUMNS = array('id', 'start_date', 'total_amount', 'source', 'end_date');

	/**
	 * @param array<string, mixed> $post
	 * @return array{order: string, dir: string, start: int, limit: int, search: string, like: string}
	 */
	public static function fromPost(array $post): array
	{
		$orderPost = $post['order'][0] ?? array();
		if (!is_array($orderPost)) {
			$orderPost = array();
		}
		$index = (int) ($orderPost['column'] ?? 0);
		$column = self::COLUMNS[$index] ?? 'id';
		if (!in_array($column, self::COLUMNS, true)) {
			$column = 'id';
		}
		$dir = strtolower((string) ($orderPost['dir'] ?? 'desc')) === 'asc' ? 'ASC' : 'DESC';
		$start = max(0, (int) ($post['start'] ?? 0));
		$limit = (int) ($post['length'] ?? 25);
		if ($limit < 1) {
			$limit = 25;
		}
		if ($limit > 200) {
			$limit = 200;
		}
		$searchPost = $post['search'] ?? array();
		$search = '';
		if (is_array($searchPost)) {
			$search = trim((string) ($searchPost['value'] ?? ''));
		}
		if (mb_strlen($search) > 80) {
			$search = mb_substr($search, 0, 80);
		}
		$like = '%' . str_replace(array('\\', '%', '_'), array('\\\\', '\\%', '\\_'), $search) . '%';

		return array(
			'order' => $column,
			'dir' => $dir,
			'start' => $start,
			'limit' => $limit,
			'search' => $search,
			'like' => $like,
		);
	}
}
