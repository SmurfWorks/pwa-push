<?php

namespace Tests\Unit;

use App\Support\Origins;
use InvalidArgumentException;
use PHPUnit\Framework\TestCase;

class OriginsTest extends TestCase
{
    public function test_it_normalizes_a_site_origin(): void
    {
        $this->assertSame('https://example.com', Origins::normalize('HTTPS://Example.com:443/'));
        $this->assertSame('http://localhost:5173', Origins::normalize('http://localhost:5173'));
        $this->assertSame('http://dosage.test', Origins::normalize('http://dosage.test'));
    }

    public function test_it_rejects_a_path_or_a_public_http_address(): void
    {
        $this->expectException(InvalidArgumentException::class);
        Origins::normalize('https://example.com/dosage');
    }

    public function test_it_rejects_http_except_on_a_local_host(): void
    {
        $this->expectException(InvalidArgumentException::class);
        Origins::normalize('http://example.com');
    }

    public function test_it_matches_a_page_on_a_registered_origin(): void
    {
        $origins = ['https://example.com', 'http://localhost:5173'];

        $this->assertTrue(Origins::matches('https://example.com/dosage/', $origins));
        $this->assertTrue(Origins::matches('http://localhost:5173/icon.png', $origins));
        $this->assertFalse(Origins::matches('https://other.example/icon.png', $origins));
        $this->assertFalse(Origins::matches('javascript:alert(1)', $origins));
    }
}
