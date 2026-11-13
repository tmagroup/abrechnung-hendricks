<?php

declare(strict_types=1);

use PHPUnit\Framework\TestCase;

/**
 * Jede Seite unter app/php muss im Menü verlinkt oder von einer anderen Seite aus erreichbar sein.
 * Ein Eintrag nur in der Aktiv-Markierung ('on') zählt nicht.
 */
final class PageReachabilityTest extends TestCase
{
	public function testEveryPageIsReachableFromMenuOrLink(): void
	{
		$pages = $this->pageNames();
		$linked = $this->linkedNames();
		$missing = array_values(array_diff($pages, $linked));

		$this->assertSame(
			array(),
			$missing,
			'Nicht im Menü und nicht verlinkt: ' . implode(', ', $missing)
		);
	}

	public function testSavedInvoiceListsAreMenuChildrenAndCrossLinked(): void
	{
		$header = (string) file_get_contents($this->phpRoot() . '/include/header.php');
		$this->assertSame(3, substr_count($header, "'label' => 'Gespeicherte Abrechnungen'"));
		$this->assertStringContainsString("'href' => 'abrechungen'", $header);
		$this->assertStringContainsString("'href' => 'abrechnung_gesamt_list'", $header);
		$this->assertStringContainsString("'href' => 'provisionsabrechung_list'", $header);

		$detail = (string) file_get_contents($this->phpRoot() . '/detailabrechnung.php');
		$gesamt = (string) file_get_contents($this->phpRoot() . '/abrechnung_gesamt.php');
		$provision = (string) file_get_contents($this->phpRoot() . '/provisionsabrechung.php');
		$this->assertStringContainsString("appUrl('abrechungen')", $detail);
		$this->assertStringContainsString('Gespeicherte Abrechnungen', $detail);
		$this->assertStringContainsString("appUrl('abrechnung_gesamt_list')", $gesamt);
		$this->assertStringContainsString("appUrl('provisionsabrechung_list')", $provision);

		foreach (array('abrechungen.php', 'abrechnung_gesamt_list.php', 'provisionsabrechung_list.php') as $file) {
			$source = (string) file_get_contents($this->phpRoot() . '/' . $file);
			$this->assertStringContainsString('Neue Abrechnung erstellen', $source, $file);
		}
	}

	public function testGesamtListKeepsItsOwnTableAndGermanAmount(): void
	{
		$ajax = (string) file_get_contents($this->phpRoot() . '/abrechnung_gesamt_list_ajax.php');
		$this->assertStringContainsString('FROM invoice2', $ajax);
		$this->assertStringContainsString('countRec("invoice2"', $ajax);
		$this->assertStringContainsString('abrechnung_gesamt/', $ajax);
		$this->assertStringNotContainsString('detailabrechnung/', $ajax);
		$this->assertStringContainsString('hc_money(', $ajax);

		$detailAjax = (string) file_get_contents($this->phpRoot() . '/abrechungen_ajax.php');
		$provisionAjax = (string) file_get_contents($this->phpRoot() . '/provisionsabrechung_list_ajax.php');
		$this->assertStringContainsString('hc_money(', $detailAjax);
		$this->assertStringContainsString('hc_money(', $provisionAjax);
		$this->assertStringContainsString('detailabrechnung/', $detailAjax);
		$this->assertStringContainsString('provisionsabrechung/', $provisionAjax);
	}

	/**
	 * @return list<string>
	 */
	private function pageNames(): array
	{
		$names = array();
		foreach (glob($this->phpRoot() . '/*.php') ?: array() as $file) {
			$name = basename($file, '.php');
			if (in_array($name, array('login', 'logout'), true)) {
				continue;
			}
			if (preg_match('/_(ajax|delete|add_update)$/', $name) === 1) {
				continue;
			}
			$names[] = $name;
		}
		sort($names);

		return $names;
	}

	/**
	 * @return list<string>
	 */
	private function linkedNames(): array
	{
		$found = array();
		$iterator = new RecursiveIteratorIterator(new RecursiveDirectoryIterator($this->phpRoot()));
		foreach ($iterator as $file) {
			if (!$file->isFile() || $file->getExtension() !== 'php') {
				continue;
			}
			$source = (string) file_get_contents($file->getPathname());
			$own = $file->getBasename('.php');
			$inHeader = str_ends_with(str_replace('\\', '/', $file->getPathname()), '/include/header.php');
			if ($inHeader && preg_match_all("/'href'\\s*=>\\s*'([a-z0-9_]+)'/", $source, $hrefs) > 0) {
				foreach ($hrefs[1] as $name) {
					$found[$name] = true;
				}
			}
			if (preg_match_all("/appUrl\\(\\s*['\"]([a-z0-9_]+)['\"]\\s*\\)/", $source, $urls) > 0) {
				foreach ($urls[1] as $name) {
					if ($name !== $own) {
						$found[$name] = true;
					}
				}
			}
			if (preg_match_all('/\$app_url\s*\.\s*[\'"]([a-z0-9_]+)/', $source, $concats) > 0) {
				foreach ($concats[1] as $name) {
					if ($name !== $own) {
						$found[$name] = true;
					}
				}
			}
		}
		$names = array_keys($found);
		sort($names);

		return $names;
	}

	private function phpRoot(): string
	{
		return dirname(__DIR__) . '/app/php';
	}
}
