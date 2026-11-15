<?php

declare(strict_types=1);

use PHPUnit\Framework\TestCase;

final class ContrastTest extends TestCase
{
	public function testDarkSurfacesAndMutedTextMeetAa(): void
	{
		$css = (string) file_get_contents(dirname(__DIR__) . '/assets/css/hendricks.css');
		$this->assertMatchesRegularExpression('/--mut:\s*#6f5c4a;/', $css);
		$this->assertMatchesRegularExpression('/\.hc-app \.top h1 \{[^}]*color:\s*#F6E7C1;/s', $css);
		$this->assertMatchesRegularExpression('/\.hc-app \.sec h2 \{[^}]*color:\s*#F6E7C1;/s', $css);
		$this->assertMatchesRegularExpression('/\.hc-step \.step-num \{[^}]*color:\s*#2a1a14;/s', $css);
		$this->assertMatchesRegularExpression('/\.hc-app svg text \{[^}]*fill:\s*#5e4c3c;/s', $css);
		$this->assertMatchesRegularExpression('/\.hc-app \.text-warning \{[^}]*color:\s*#7a4f12\s*!important;/s', $css);
		$this->assertMatchesRegularExpression('/\.hc-app \.text-success \{[^}]*color:\s*#245c2a\s*!important;/s', $css);
		$this->assertStringContainsString('.hc-app h1, .hc-app h2, .hc-app h3, .hc-app h4 { color: var(--bordd); }', $css);

		$cases = array(
			array('Begrüßung auf dem Banner', 'F6E7C1', '6a1f1a', 4.5),
			array('Abschnittsbanner', 'F6E7C1', '7E221C', 4.5),
			array('Nebentext auf Pergament', '6f5c4a', 'EFE7D6', 4.5),
			array('Schritt auf hellem Grund', '6f5c4a', 'f3eadc', 4.5),
			array('Schrittzahl', '2a1a14', 'CCB28E', 4.5),
			array('Diagrammbeschriftung', '5e4c3c', 'FBF7EE', 4.5),
			array('Hinweis Klartext', '7a4f12', 'FBF7EE', 4.5),
			array('Hinweis gehasht', '245c2a', 'FBF7EE', 4.5),
			array('Nebentext auf der Karte', '6f5c4a', 'FBF7EE', 4.5),
		);
		foreach ($cases as $case) {
			$ratio = $this->contrast($case[1], $case[2]);
			$this->assertGreaterThanOrEqual($case[3], $ratio, $case[0] . ' ' . number_format($ratio, 2, '.', ''));
		}
	}

	private function contrast(string $foreground, string $background): float
	{
		$light = $this->luminance($foreground);
		$dark = $this->luminance($background);
		if ($light < $dark) {
			$swap = $light;
			$light = $dark;
			$dark = $swap;
		}

		return ($light + 0.05) / ($dark + 0.05);
	}

	private function luminance(string $hex): float
	{
		$hex = ltrim($hex, '#');
		$channels = array(hexdec(substr($hex, 0, 2)), hexdec(substr($hex, 2, 2)), hexdec(substr($hex, 4, 2)));
		$linear = array();
		foreach ($channels as $channel) {
			$value = $channel / 255;
			$linear[] = $value <= 0.04045 ? $value / 12.92 : (($value + 0.055) / 1.055) ** 2.4;
		}

		return 0.2126 * $linear[0] + 0.7152 * $linear[1] + 0.0722 * $linear[2];
	}
}
