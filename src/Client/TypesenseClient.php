<?php

declare(strict_types=1);

namespace Symfony\UX\Typesense\Client;

use Typesense\Aliases;
use Typesense\Client;
use Typesense\Collections;
use Typesense\Debug;
use Typesense\Health;
use Typesense\Keys;
use Typesense\Metrics;
use Typesense\MultiSearch;
use Typesense\Operations;

class TypesenseClient
{
    private $client;

    public function __construct(string $url, ?string $apiKey = null)
    {
        if ($url === 'null') {
            return;
        }

        $urlParsed = parse_url($url);

        if(!$apiKey)
            throw new \Exception("Typesense API key not defined.");

        $this->client = new Client([
            'nodes' => [
                [
                    'host'     => $urlParsed['host'] ?? null,
                    'port'     => $urlParsed['port'] ?? 3306,
                    'protocol' => $urlParsed['scheme'] ?? "https",
                ],
            ],
            'api_key'                    => $apiKey,
            'connection_timeout_seconds' => 5,
        ]);
    }

    public function getCollections(): ?Collections
    {
        if (!$this->client) {
            return null;
        }

        return $this->client->collections;
    }

    public function getAliases(): ?Aliases
    {
        if (!$this->client) {
            return null;
        }

        return $this->client->aliases;
    }

    public function getKeys(): ?Keys
    {
        if (!$this->client) {
            return null;
        }

        return $this->client->keys;
    }

    public function getDebug(): ?Debug
    {
        if (!$this->client) {
            return null;
        }

        return $this->client->debug;
    }

    public function getMetrics(): ?Metrics
    {
        if (!$this->client) {
            return null;
        }

        return $this->client->metrics;
    }

    public function getHealth(): ?Health
    {
        if (!$this->client) {
            return null;
        }

        return $this->client->health;
    }

    public function getOperations(): ?Operations
    {
        if (!$this->client) {
            return null;
        }

        return $this->client->operations;
    }

    public function getMultiSearch(): ?MultiSearch
    {
        if (!$this->client) {
            return null;
        }

        return $this->client->multiSearch;
    }

    /**
     * This allow to be use to use new Typesense\Client functions
     * before we update this client.
     */
    public function __call($name, $arguments)
    {
        if (!$this->client) {
            return null;
        }

        return $this->client->{$name}(...$arguments);
    }

    public function __get($name)
    {
        if (!$this->client) {
            return null;
        }

        return $this->client->{$name};
    }

    public function isOperationnal(): bool
    {
        return $this->client !== null;
    }
}