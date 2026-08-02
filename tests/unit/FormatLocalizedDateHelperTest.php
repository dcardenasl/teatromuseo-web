<?php

declare(strict_types=1);

namespace Tests\Unit;

use CodeIgniter\Test\CIUnitTestCase;

/**
 * @internal
 */
final class FormatLocalizedDateHelperTest extends CIUnitTestCase
{
    public function testFormatsMonthNameForEachSupportedLanguage(): void
    {
        // 2026-08-04 chosen deliberately: exercises the exact bug reported live
        // (public.d date('d M Y', ...) always renders English month abbreviations
        // regardless of page language).
        $this->assertSame('4 ago 2026', format_localized_date('2026-08-04', 'es'));
        $this->assertSame('Aug 4, 2026', format_localized_date('2026-08-04', 'en'));
        $this->assertSame('4 août 2026', format_localized_date('2026-08-04', 'fr'));
    }

    public function testUnknownLanguageFallsBackToSpanish(): void
    {
        $this->assertSame('4 ago 2026', format_localized_date('2026-08-04', 'xx'));
    }

    public function testEmptyOrUnparsableValueReturnsEmptyString(): void
    {
        $this->assertSame('', format_localized_date('', 'es'));
        $this->assertSame('', format_localized_date('not-a-date', 'es'));
    }

    public function testLocaleMapIsSharedBetweenHelperAndTrait(): void
    {
        $this->assertSame('es_ES', localized_date_intl_locale('es'));
        $this->assertSame('en_US', localized_date_intl_locale('en'));
        $this->assertSame('fr_FR', localized_date_intl_locale('fr'));
        $this->assertSame('pt_PT', localized_date_intl_locale('pt'));
        $this->assertSame('es_ES', localized_date_intl_locale('xx'));
    }
}
