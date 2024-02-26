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

use Ahc\Json\Comment as CommentedJsonDecoder;
use pocketmine\plugin\PluginBase;
use pocketmine\resourcepacks\ResourcePackException;
use pocketmine\scheduler\AsyncTask;
use pocketmine\Server;
use pocketmine\utils\AssumptionFailedError;
use Symfony\Component\Filesystem\Path;

use function file_exists;
use function file_get_contents;
use function igbinary_serialize;
use function igbinary_unserialize;
use function is_array;
use function is_dir;
use function is_file;
use function json_decode;
use function json_encode;
use function scandir;
use function str_ends_with;
use function str_starts_with;

use function time;

use const SCANDIR_SORT_NONE;

final class AddonArchiveAsyncTask extends AsyncTask{
	private const TLS_KEY_PLUGIN = "plugin";

	private string $pluginResourcePath;
	private string $resourcePackDir;

	public function __construct(PluginBase $plugin){
		$this->storeLocal(self::TLS_KEY_PLUGIN, $plugin);
		$this->pluginResourcePath = $plugin->getResourcePath("addons");
		$this->resourcePackDir = Server::getInstance()->getResourcePackManager()->getPath();
	}

	public function onRun() : void{
		if(!file_exists($this->pluginResourcePath) || !is_dir($this->pluginResourcePath)){
			throw new AssumptionFailedError(
				"The 'addons' folder could not be found in the resources in the plugin : $this->pluginResourcePath"
			);
		}

		$result = [];
		foreach(scandir($this->pluginResourcePath, SCANDIR_SORT_NONE) as $innerPath){
			if($innerPath === ".."){ // "." does not exclude, for 'addons' folder itself to be included
				continue;
			}

			$addonDir = Path::join($this->pluginResourcePath, $innerPath);
			$manifestPath = Path::join($addonDir, "manifest.json");
			if(!is_dir($addonDir) || !file_exists($manifestPath) || !is_file($manifestPath)){
				continue;
			}

			$manifest = json_decode(file_get_contents($manifestPath), true);
			if(!is_array($manifest) || !isset($manifest["header"]["uuid"])){
				continue;
			}

			$uuid = $manifest["header"]["uuid"];
			$output = Path::join($this->resourcePackDir, "\{$uuid}.zip");

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
			$result[$addonDir] = $uuid;
		}

		$this->setResult(igbinary_serialize($result));
	}

	public function onCompletion() : void{
		/** @var PluginBase $plugin */
		$plugin = $this->fetchLocal(self::TLS_KEY_PLUGIN);
		$result = igbinary_unserialize($this->getResult());
		InnerAddons::registerResourcePack($plugin, ...$result);
	}
}
