<?php

declare(strict_types=1);

namespace Recado\Sdk\Laravel\Facades;

use Illuminate\Support\Facades\Facade;
use Recado\Sdk\RecadoClient;

/**
 * Facade proxying the container-bound RecadoClient singleton, so an app can
 * reach the API through static calls (Recado::contacts()->..., Recado::send()
 * ->..., etc.) without injecting the client. It resolves the very same instance
 * you would receive by type-hinting RecadoClient.
 *
 * @method static \Recado\Sdk\Resources\SendResource send()
 * @method static \Recado\Sdk\Resources\ContactsResource contacts()
 * @method static \Recado\Sdk\Resources\ListsResource lists()
 * @method static \Recado\Sdk\Resources\TagsResource tags()
 * @method static \Recado\Sdk\Resources\TemplatesResource templates()
 * @method static \Recado\Sdk\Resources\MessagesResource messages()
 * @method static \Recado\Sdk\Resources\CampaignsResource campaigns()
 * @method static \Recado\Sdk\Resources\NotificationsResource notifications()
 * @method static \Recado\Sdk\Resources\PushTokensResource push()
 * @method static \Recado\Sdk\Resources\SandboxResource sandbox()
 *
 * @see \Recado\Sdk\RecadoClient
 */
final class Recado extends Facade
{
    protected static function getFacadeAccessor(): string
    {
        return RecadoClient::class;
    }
}
