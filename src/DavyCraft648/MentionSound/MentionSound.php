<?php

namespace DavyCraft648\MentionSound;

use pocketmine\command\{Command, CommandSender};
use dktapps\pmforms\{CustomForm, CustomFormResponse, element\Input, element\Toggle, FormIcon, MenuForm, MenuOption};
use pocketmine\event\Listener;
use pocketmine\event\player\PlayerChatEvent;
use pocketmine\network\mcpe\protocol\PlaySoundPacket;
use pocketmine\player\Player;
use pocketmine\plugin\PluginBase;
use pocketmine\utils\{Config, TextFormat};

class MentionSound extends PluginBase implements Listener
{
	/** @var string[] */
	private array $soundList = [];

	public function onEnable(): void
	{
		$this->saveResource("soundlist.yml");
		$soundConfig = new Config($this->getDataFolder() . "soundlist.yml", Config::YAML);
		$this->soundList = $soundConfig->getAll();
		$this->getServer()->getPluginManager()->registerEvents($this, $this);
	}

	public function onCommand(CommandSender $sender, Command $command, string $label, array $args): bool
	{
		if (!($sender instanceof Player)) return false;
		/** @var Player $player */
		$player = $sender;
		if (isset($args[0])) {
			if (mb_strtolower($args[0]) === "settings") {
				$this->settingsForm($player);
				return true;
			} elseif (mb_strtolower($args[0]) === "soundlist") {
				$this->soundListForm($player);
				return true;
			}
		}
		$this->helpForm($player);
		return true;
	}

	public function helpForm(Player $player)
	{
		$form = new MenuForm(
			"MentionSound",
			"",
			[
				new MenuOption("Settings", new FormIcon("textures/ui/settings_glyph_color_2x", FormIcon::IMAGE_TYPE_PATH)),
				new MenuOption("Sound List", new FormIcon("textures/ui/sound_glyph_2x", FormIcon::IMAGE_TYPE_PATH))
			],
			function(Player $player, int $selected) : void {
				if ($selected === 0) {
					$this->settingsForm($player);
				} elseif ($selected === 1) {
					$this->soundListForm($player);
				}
			}
		);
		$player->sendForm($form);
	}

	private function soundListForm(Player $player)
	{
		$form = new MenuForm(
			"Sound List",
			implode("\n", $this->soundList),
			[],
			function(Player $player, int $selected) : void {}
		);
		$player->sendForm($form);
	}

	private function settingsForm(Player $player)
	{
		$userSettings = $this->getUserSettings($player->getName());
		$form = new CustomForm(
			"MentionSound Settings",
			[
				new Toggle("nameMention", "@{$player->getDisplayName()} Mention", $userSettings->get("nameMention")),
				new Input("nameMentionSound", "@{$player->getDisplayName()} Mention Sound", $userSettings->get("nameMentionSound"), $userSettings->get("nameMentionSound")),
				new Toggle("hereMention", "@here Mention", $userSettings->get("hereMention")),
				new Input("hereMentionSound", "@here Mention Sound", $userSettings->get("hereMentionSound"), $userSettings->get("hereMentionSound")),
				new Input("pitch", "Pitch", $userSettings->get("pitch"), $userSettings->get("pitch")),
			],
			function(Player $player, CustomFormResponse $response) use ($userSettings) : void {
				$nameMentionSound = $response->getString("nameMentionSound");
				if (!empty($nameMentionSound) and !in_array($nameMentionSound, $this->soundList)) {
					$player->sendMessage(TextFormat::RED . "Sound $nameMentionSound not found");
					return;
				}

				$hereMentionSound = $response->getString("hereMentionSound");
				if (!empty($hereMentionSound) and !in_array($hereMentionSound, $this->soundList)) {
					$player->sendMessage(TextFormat::RED . "Sound $hereMentionSound not found");
					return;
				}

				$pitch = $response->getString("pitch");
				if (!is_numeric($response->getString("pitch"))) {
					$player->sendMessage(TextFormat::RED . "Pitch must be a number");
					return;
				}

				$nameMentionSound = empty($nameMentionSound) ? $userSettings->get("nameMentionSound") : $nameMentionSound;
				$hereMentionSound = empty($hereMentionSound) ? $userSettings->get("hereMentionSound") : $hereMentionSound;
				$this->setUserSettings(
					$player->getName(),
					$response->getBool("nameMention"), $response->getBool("hereMention"),
					$nameMentionSound, $hereMentionSound, (float) $pitch
				);
				$player->sendMessage(TextFormat::GREEN . "Your Settings was successfully saved");
			}
		);
		$player->sendForm($form);
	}

	private function setUserSettings(string $name, bool $nameMention, bool $hereMention, string $nameMentionSound, string $hereMentionSound, float $pitch)
	{
		$userSettings = $this->getUserSettings($name);
		$userSettings->set("nameMention", $nameMention);
		$userSettings->set("hereMention", $hereMention);
		$userSettings->set("nameMentionSound", $nameMentionSound);
		$userSettings->set("hereMentionSound", $hereMentionSound);
		$userSettings->set("pitch", $pitch);
		$userSettings->save();
	}

	public function getSoundList(): array
	{
		return $this->soundList;
	}

	/**
	 * @param PlayerChatEvent $event
	 */
	public function onChat(PlayerChatEvent $event): void
	{
		if (str_contains($event->getMessage(), "@")) {
			foreach ($this->getServer()->getOnlinePlayers() as $player) {
				$userSettings = $this->getUserSettings($player->getName());
				$pos = $player->getPosition();
				if (str_contains($event->getMessage(), "@{$player->getDisplayName()}") and $userSettings->get("nameMention")) {
					$player->getNetworkSession()->sendDataPacket(PlaySoundPacket::create(
						(string) $userSettings->get("nameMentionSound"),
						$pos->x, $pos->y, $pos->z,
						1.0, (float) $userSettings->get("pitch")
					));
				}
				elseif (str_contains($event->getMessage(), "@here") and $userSettings->get("hereMention")) {
					$player->getNetworkSession()->sendDataPacket(PlaySoundPacket::create(
						(string) $userSettings->get("hereMentionSound"),
						$pos->x, $pos->y, $pos->z,
						1.0, (float) $userSettings->get("pitch")
					));
				}
			}
		}
	}

	/**
	 * @param string $name Player Name
	 * @return Config
	 */
	public function getUserSettings(string $name): Config
	{
		if (!is_dir($this->getDataFolder() . "settings"))
			mkdir($this->getDataFolder() . "settings");
		return new Config($this->getDataFolder() . "settings/$name.yml", Config::YAML, [
			"nameMention" => true,
			"hereMention" => false,
			"nameMentionSound" => "note.pling",
			"hereMentionSound" => "note.pling",
			"pitch" => 1
		]);
	}
}