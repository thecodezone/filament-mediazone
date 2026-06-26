<?php

declare(strict_types=1);

namespace Codezone\MediaZone\Tests\Unit;

use Codezone\MediaZone\Filament\Resources\MediaResource\Pages\ListMedia;
use Codezone\MediaZone\Livewire\MediaListing;
use Codezone\MediaZone\Tests\TestCase;

class MediaListingTest extends TestCase
{
    public function test_get_edit_url_returns_null_when_resource_getUrl_throws(): void
    {
        // No Filament panel registered, so getUrl() will throw — getEditUrl() must return null
        $component = new MediaListing;

        $this->assertNull($component->getEditUrl(1));
    }

    public function test_set_sort_ignores_disallowed_columns(): void
    {
        $component = new MediaListing;
        $component->sort = 'created_at';

        $component->setSort('password');

        $this->assertSame('created_at', $component->sort);
    }

    public function test_set_sort_accepts_allowed_columns(): void
    {
        $component = new MediaListing;

        $component->setSort('name');

        $this->assertSame('name', $component->sort);
    }

    public function test_set_sort_toggles_direction_on_same_column(): void
    {
        $component = new MediaListing;
        $component->sort = 'name';
        $component->sortDir = 'asc';

        $component->setSort('name');

        $this->assertSame('desc', $component->sortDir);
    }

    // ListMedia (Filament page) uses the same blade partial and needs the same method

    public function test_list_media_get_edit_url_returns_null_when_resource_getUrl_throws(): void
    {
        $page = new ListMedia;

        $this->assertNull($page->getEditUrl(1));
    }

    public function test_list_media_set_sort_ignores_disallowed_columns(): void
    {
        $page = new ListMedia;
        $page->mediaSort = 'created_at';

        $page->setSort('password');

        $this->assertSame('created_at', $page->mediaSort);
    }

}
