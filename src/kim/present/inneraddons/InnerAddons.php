<?php

/**
 *
 *  ____                           _   _  ___
 * |  _ \ _ __ ___  ___  ___ _ __ | |_| |/ (_)_ __ ___
 * | |_) | '__/ _ \/ __|/ _ \ '_ \| __| ' /| | '_ ` _ \
 * |  __/| | |  __/\__ \  __/ | | | |_| . \| | | | | | |
 * |_|   |_|  \___||___/\___|_| |_|\__|_|\_\_|_| |_| |_|
 *
 * This program is free software: you can redistribute it and/or modify
 * it under the terms of the MIT License. see <https://opensource.org/licenses/MIT>.
 *
 * @author       PresentKim (debe3721@gmail.com)
 * @link         https://github.com/PresentKim
 * @license      https://opensource.org/licenses/MIT MIT License
 *
 *   (\ /)
 *  ( . .) ♥
 *  c(")(")
 *
 * @noinspection PhpUnused
 */

declare(strict_types=1);

namespace kim\present\inneraddons;

use alvin0319\RemoteResourcePack\Loader as RemoteResourcePackPlugin;
use pocketmine\plugin\PluginBase;
use pocketmine\resourcepacks\ZippedResourcePack;
use pocketmine\Server;
use Symfony\Component\Filesystem\Path;

final class InnerAddons{
	public const CDN_URL = "http://27.102.92.200:3030/"; //TODO: from config

	public static function archiveAddonsAsync(PluginBase $plugin) : void{
		Server::getInstance()->getAsyncPool()->submitTask(new AddonArchiveAsyncTask($plugin));
	}

	public static function registerResourcePack(PluginBase $plugin, string ...$uuidList) : void{
		$server = Server::getInstance();
		$resourePackManager = $server->getResourcePackManager();
		$resourcePackDir = $resourePackManager->getPath();
		$resourcePacks = $resourePackManager->getResourceStack();
		foreach($uuidList as $uuid){
			$pack = new ZippedResourcePack(Path::join($resourcePackDir, "\{$uuid}.zip"));
			$resourcePacks[] = $pack;

			$plugin->getLogger()->info(
				"Inner addon registered to server: {$pack->getPackName()}_v{$pack->getPackVersion()} ({$pack->getPackId()})"
			);
		}
		$resourePackManager->setResourceStack($resourcePacks);

		// Register to RemoteResourcePack plugin for CDN supports
		$cdnPlugin = $server->getPluginManager()->getPlugin("RemoteResourcePack");
		if($cdnPlugin instanceof RemoteResourcePackPlugin){
			\Closure::bind( //HACK: Closure bind hack to access inaccessible members
				closure: static function() use ($cdnPlugin, $uuidList) : void{
					foreach($uuidList as $uuid){
						$cdnPlugin->resourcePacks[$uuid] = InnerAddons::CDN_URL . "{" . $uuid . "}";
					}
				},
				newThis: null,
				newScope: RemoteResourcePackPlugin::class
			)();
			$plugin->getLogger()->info("All inner addons registered to RemoteResourcePack plugin");
		}
	}
}
