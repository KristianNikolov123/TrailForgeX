<?php
use PHPUnit\Framework\TestCase;

function difficultyScore($distanceKm, $elevGainM) {
    $d = $distanceKm / 10;
    $e = $elevGainM / 300;
    $raw = $d * 0.45 + $e * 0.55;
    return max(0, min(10, $raw * 10));
}

class DifficultyTest extends TestCase {
    
    public function testEasyRoute() {
        $score = difficultyScore(2, 20);
        $this->assertLessThan(2.5, $score, "Маршрутът трябва да е Easy");
    }

    public function testHardRoute() {
        $score = difficultyScore(25, 800);
        $this->assertGreaterThan(7.0, $score, "Маршрутът трябва да е Hard или по-нагоре");
    }

    public function testMaxLimit() {
        $score = difficultyScore(200, 5000);
        $this->assertEquals(10, $score, "Резултатът не трябва да надвишава 10");
    }
}