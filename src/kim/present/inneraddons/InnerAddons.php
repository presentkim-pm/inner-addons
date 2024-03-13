<?php
/** @noinspection IncorrectRandomRangeInspection */

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

use Ahc\Json\Comment as CommentedJsonDecoder;
use pocketmine\plugin\PluginBase;
use pocketmine\resourcepacks\ResourcePackException;
use pocketmine\resourcepacks\ZippedResourcePack;
use pocketmine\Server;
use pocketmine\utils\AssumptionFailedError;
use Symfony\Component\Filesystem\Path;

use function file_exists;
use function file_get_contents;
use function is_array;
use function is_dir;
use function is_file;
use function json_decode;
use function json_encode;
use function str_ends_with;
use function str_starts_with;
use function time;

final class InnerAddons{
	public static function register(PluginBase $plugin, string $resourcePath = "addons") : void{
		$addonDir = $plugin->getResourcePath($resourcePath);
		if(!file_exists($addonDir) || !is_dir($addonDir)){
			throw new AssumptionFailedError(
				"Error on {$plugin->getName()}/$resourcePath addon loading: not found '$resourcePath' directory"
			);
		}
		$manifestPath = Path::join($addonDir, "manifest.json");
		if(!file_exists($manifestPath) || !is_file($manifestPath)){
			throw new AssumptionFailedError(
				"Error on {$plugin->getName()}/$resourcePath addon loading: not found 'manifest.json'"
			);
		}

		$manifest = json_decode(file_get_contents($manifestPath), true);
		if(!is_array($manifest) || !isset($manifest["header"]["uuid"])){
			throw new AssumptionFailedError(
				"Error on {$plugin->getName()}/$resourcePath addon loading: failed parsing 'manifest.json'"
			);
		}

		$uuid = $manifest["header"]["uuid"];
		$resourePackManager = Server::getInstance()->getResourcePackManager();
		if($resourePackManager->getPackById($uuid) !== null){
			$plugin->getLogger()->warning("Resource pack with UUID $uuid is already registered");
			return;
		}

		$resourcePackDir = $resourePackManager->getPath();
		$resourcePacks = $resourePackManager->getResourceStack();
		$output = Path::join($resourcePackDir, "_inner.$uuid.zip");

		$archive = new \ZipArchive();
		$archive->open($output, \ZipArchive::CREATE | \ZipArchive::OVERWRITE);

		/** @var \SplFileInfo $fileInfo */
		foreach(new \RecursiveIteratorIterator(new \RecursiveDirectoryIterator($addonDir)) as $fileInfo){
			if(!$fileInfo->isFile()){
				continue;
			}

			$realPath = $fileInfo->getPathname();
			$innerPath = Path::makeRelative($realPath, $addonDir);
			if(str_starts_with($innerPath, ".")){
				continue;
			}

			$contents = file_get_contents($realPath);
			if($contents === false){
				throw new ResourcePackException("Failed to open $realPath file");
			}

			if(str_ends_with($innerPath, ".json")){
				try{
					$contents = json_encode((new CommentedJsonDecoder())->decode($contents));
				}catch(\RuntimeException){
				}
			}
			$archive->addFromString($innerPath, $contents);
			$archive->setCompressionName($innerPath, \ZipArchive::CM_DEFLATE64);
			$archive->setMtimeName($innerPath, time());
		}
		$archive->close();

		$pack = new ZippedResourcePack($output);
		$resourcePacks[] = $pack;
		$plugin->getLogger()->info(
			"Registered {$pack->getPackName()}_v{$pack->getPackVersion()} addon registered to server: $uuid"
		);
		$resourePackManager->setResourceStack($resourcePacks);
	}
}
