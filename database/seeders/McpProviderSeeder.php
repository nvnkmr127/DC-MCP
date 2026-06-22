<?php

namespace Database\Seeders;

use Illuminate\Database\Console\Seeds\WithoutModelEvents;
use Illuminate\Database\Seeder;

class McpProviderSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        $providers = [
            [
                'name' => 'Gmail',
                'slug' => 'gmail',
                'is_active' => true,
                'description' => 'Connect to Google Mail for email sync and intelligence.',
                'adapter_class' => \App\Modules\MCP\Adapters\GmailAdapter::class,
            ],
            [
                'name' => 'Google Calendar',
                'slug' => 'google_calendar',
                'is_active' => true,
                'description' => 'Connect to Google Calendar to manage schedules and meetings.',
                'adapter_class' => \App\Modules\MCP\Adapters\GoogleCalendarAdapter::class,
            ],
            [
                'name' => 'Notion',
                'slug' => 'notion',
                'is_active' => true,
                'description' => 'Connect to Notion to sync documents and databases.',
                'adapter_class' => \App\Modules\MCP\Adapters\NotionAdapter::class,
            ],
            [
                'name' => 'Zoho Cliq',
                'slug' => 'zoho_cliq',
                'is_active' => true,
                'description' => 'Connect to Zoho Cliq for messaging and notifications.',
                'adapter_class' => \App\Modules\MCP\Adapters\ZohoCliqAdapter::class,
            ],
            [
                'name' => 'Meta Ads',
                'slug' => 'meta_ads',
                'is_active' => true,
                'description' => 'Connect to Meta Ads for performance marketing metrics.',
                'adapter_class' => \App\Modules\MCP\Adapters\MetaAdsAdapter::class,
            ],
            [
                'name' => 'Make (Integromat)',
                'slug' => 'make',
                'is_active' => true,
                'description' => 'Connect to Make to trigger workflows and scenarios.',
                'adapter_class' => \App\Modules\MCP\Adapters\MakeAdapter::class,
            ],
        ];

        foreach ($providers as $provider) {
            if (!isset($provider['id'])) {
                $provider['id'] = (string) \Illuminate\Support\Str::uuid();
            }
            
            \App\Modules\MCP\Models\McpProvider::updateOrCreate(
                ['slug' => $provider['slug']],
                $provider
            );
        }
    }
}
