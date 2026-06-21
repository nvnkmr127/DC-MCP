<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\Log;

class MonitorInfrastructureHealth extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'monitor:infrastructure';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Monitors host infrastructure (Disk, Memory, SSL Certs) for alerts.';

    /**
     * Execute the console command.
     */
    public function handle()
    {
        $this->checkDiskSpace();
        $this->checkMemoryUtilization();
        $this->checkSslCertificate();
    }

    private function checkDiskSpace()
    {
        $path = base_path();
        $free = disk_free_space($path);
        $total = disk_total_space($path);
        
        if ($total > 0) {
            $usedPercent = 100 - (($free / $total) * 100);
            if ($usedPercent >= 90) {
                Log::critical(sprintf("Infrastructure Alert: Disk usage is critically high at %.2f%%.", $usedPercent));
            } else {
                $this->info(sprintf("Disk usage is normal: %.2f%%.", $usedPercent));
            }
        }
    }

    private function checkMemoryUtilization()
    {
        // Try reading /proc/meminfo (works on Linux systems)
        if (is_readable('/proc/meminfo')) {
            $meminfo = file_get_contents('/proc/meminfo');
            preg_match('/MemTotal:\s+(\d+) kB/', $meminfo, $totalMatches);
            preg_match('/MemAvailable:\s+(\d+) kB/', $meminfo, $availMatches);
            
            if (isset($totalMatches[1]) && isset($availMatches[1])) {
                $total = (int) $totalMatches[1];
                $available = (int) $availMatches[1];
                $usedPercent = 100 - (($available / $total) * 100);
                
                if ($usedPercent >= 90) {
                    Log::critical(sprintf("Infrastructure Alert: Server memory utilization is critically high at %.2f%%.", $usedPercent));
                } else {
                    $this->info(sprintf("Memory utilization is normal: %.2f%%.", $usedPercent));
                }
                return;
            }
        }
        
        $this->info("Memory utilization check skipped (not supported on this host environment).");
    }

    private function checkSslCertificate()
    {
        $url = config('app.url');
        
        if (str_starts_with($url, 'https://')) {
            try {
                $host = parse_url($url, PHP_URL_HOST);
                $get = stream_context_create(["ssl" => ["capture_peer_cert" => true]]);
                $read = stream_socket_client("ssl://".$host.":443", $errno, $errstr, 10, STREAM_CLIENT_CONNECT, $get);
                $cert = stream_context_get_params($read);
                $certinfo = openssl_x509_parse($cert['options']['ssl']['peer_certificate']);
                
                $validTo = $certinfo['validTo_time_t'];
                $daysLeft = ($validTo - time()) / 86400;
                
                if ($daysLeft < 14) {
                    Log::critical(sprintf("Infrastructure Alert: SSL Certificate for %s expires in %d days!", $host, (int)$daysLeft));
                } else {
                    $this->info(sprintf("SSL Certificate for %s is valid for another %d days.", $host, (int)$daysLeft));
                }
            } catch (\Exception $e) {
                $this->error("Failed to check SSL certificate for {$url}: " . $e->getMessage());
            }
        } else {
            $this->info("SSL Certificate check skipped (app.url is not HTTPS).");
        }
    }
}
