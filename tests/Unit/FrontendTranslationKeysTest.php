<?php

namespace Tests\Unit;

use PHPUnit\Framework\TestCase;
use RecursiveDirectoryIterator;
use RecursiveIteratorIterator;
use SplFileInfo;

class FrontendTranslationKeysTest extends TestCase
{
    /**
     * @return array<string, mixed>
     */
    private function translations(): array
    {
        $path = dirname(__DIR__, 2).'/resources/js/i18n/locales/es.json';
        $decoded = json_decode((string) file_get_contents($path), true);

        $this->assertIsArray($decoded);
        $this->assertNotEmpty($decoded);

        return $decoded;
    }

    /**
     * @param  array<string, mixed>  $translations
     * @return array<string, string>
     */
    private function flatten(array $translations, string $prefix = ''): array
    {
        $flat = [];

        foreach ($translations as $key => $value) {
            $path = $prefix === '' ? (string) $key : $prefix.'.'.$key;

            if (is_array($value)) {
                $flat = array_merge($flat, $this->flatten($value, $path));

                continue;
            }

            $flat[$path] = (string) $value;
        }

        return $flat;
    }

    /**
     * @return list<string>
     */
    private function frontendSourceFiles(): array
    {
        $root = dirname(__DIR__, 2).'/resources/js';
        $files = [];
        $iterator = new RecursiveIteratorIterator(
            new RecursiveDirectoryIterator($root),
        );

        /** @var SplFileInfo $file */
        foreach ($iterator as $file) {
            if (! $file->isFile()) {
                continue;
            }

            if (! in_array($file->getExtension(), ['ts', 'tsx'], true)) {
                continue;
            }

            $files[] = $file->getPathname();
        }

        return $files;
    }

    /**
     * @return list<string>
     */
    private function usedTranslationKeys(): array
    {
        $keys = [];

        foreach ($this->frontendSourceFiles() as $path) {
            $contents = (string) file_get_contents($path);

            preg_match_all(
                "/(?<![A-Za-z_\$])t\(\s*['\"]([^'\"]+)['\"]/",
                $contents,
                $tCalls,
            );

            foreach ($tCalls[1] as $key) {
                $keys[] = $key;
            }

            preg_match_all(
                "/i18nKey=['\"]([^'\"]+)['\"]/",
                $contents,
                $i18nKeys,
            );

            foreach ($i18nKeys[1] as $key) {
                $keys[] = $key;
            }

            preg_match_all(
                "/(?:title|description|toggleText):\s*['\"]([a-z][a-zA-Z0-9]+(?:\.[a-zA-Z0-9]+)+)['\"]/",
                $contents,
                $layoutKeys,
            );

            foreach ($layoutKeys[1] as $key) {
                $keys[] = $key;
            }
        }

        $unique = array_values(array_unique($keys));
        sort($unique);

        return $unique;
    }

    public function test_frontend_translation_keys_exist_in_spanish_locale(): void
    {
        $flat = $this->flatten($this->translations());
        $missing = [];

        foreach ($this->usedTranslationKeys() as $key) {
            if (isset($flat[$key])) {
                continue;
            }

            if (isset($flat[$key.'_one']) || isset($flat[$key.'_other'])) {
                continue;
            }

            $missing[] = $key;
        }

        $this->assertSame(
            [],
            $missing,
            'Missing Spanish translations: '.implode(', ', $missing),
        );
    }

    public function test_dynamic_admin_translation_groups_are_complete(): void
    {
        $flat = $this->flatten($this->translations());

        $required = [
            'admin.userStatus.active',
            'admin.userStatus.pending',
            'admin.weekdays.monday',
            'admin.weekdays.sunday',
            'admin.weekdaysShort.monday',
            'admin.weekdaysShort.sunday',
            'admin.attendance.mark',
            'admin.attendance.unmarked',
            'admin.attendance.present',
            'admin.attendance.absent',
            'admin.attendance.late',
            'admin.attendance.excused',
            'admin.sessionTypes.workshop',
            'admin.sessionTypes.privateClass',
            'admin.sessionTypes.event',
            'admin.paymentMethods.cash',
            'admin.paymentMethods.transfer',
            'admin.paymentMethods.other',
        ];

        foreach ($required as $key) {
            $this->assertArrayHasKey($key, $flat, "Missing dynamic key [{$key}]");
            $this->assertNotSame($key, $flat[$key]);
        }
    }
}
