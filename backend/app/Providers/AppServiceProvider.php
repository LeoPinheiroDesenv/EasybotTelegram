<?php

namespace App\Providers;

use App\Models\Transaction;
use App\Observers\TransactionObserver;
use Illuminate\Support\ServiceProvider;
use Illuminate\Support\Facades\Storage;
use League\Flysystem\Filesystem;

class AppServiceProvider extends ServiceProvider
{
    /**
     * Register any application services.
     */
    public function register(): void
    {
        //
    }

    /**
     * Bootstrap any application services.
     */
    public function boot(): void
    {
        // Registra observer para Transaction
        Transaction::observe(TransactionObserver::class);

        // Registra driver FTP (verifica se as classes existem)
        if (class_exists(\League\Flysystem\Ftp\FtpAdapter::class) && 
            class_exists(\League\Flysystem\Ftp\FtpConnectionOptions::class)) {
            Storage::extend('ftp', function ($app, $config) {
                try {
                    $adapter = new \League\Flysystem\Ftp\FtpAdapter(
                        \League\Flysystem\Ftp\FtpConnectionOptions::fromArray([
                            'host' => $config['host'],
                            'root' => $config['root'] ?? '/',
                            'username' => $config['username'],
                            'password' => $config['password'],
                            'port' => $config['port'] ?? 21,
                            'ssl' => $config['ssl'] ?? false,
                            'timeout' => $config['timeout'] ?? 30,
                            'passive' => $config['passive'] ?? true,
                        ])
                    );

                    return new Filesystem($adapter);
                } catch (\Exception $e) {
                    \Log::error('Erro ao registrar driver FTP: ' . $e->getMessage());
                    throw $e;
                }
            });
        }

        // Registra driver SFTP (se o pacote estiver instalado)
        if (class_exists(\League\Flysystem\PhpseclibV3\SftpAdapter::class)) {
            Storage::extend('sftp', function ($app, $config) {
                $provider = new \League\Flysystem\PhpseclibV3\SftpConnectionProvider(
                    $config['host'],
                    $config['username'],
                    $config['password'] ?? null,
                    $config['privateKey'] ?? null,
                    $config['passphrase'] ?? null,
                    $config['port'] ?? 22,
                    $config['useAgent'] ?? false,
                    $config['timeout'] ?? 30,
                    $config['maxTries'] ?? 4,
                    $config['hostFingerprint'] ?? null,
                    $config['connectivityChecker'] ?? null,
                    $config['preferredAlgorithms'] ?? null,
                    $config['disableStatCache'] ?? false
                );

                $adapter = new \League\Flysystem\PhpseclibV3\SftpAdapter(
                    $provider,
                    $config['root'] ?? '/',
                    $config['visibility'] ?? null,
                    $config['directoryPerm'] ?? 0755
                );

                return new Filesystem($adapter);
            });
        }
    }
}
