<?php

namespace DavyCraft648\MentionSound;

use jojoe77777\FormAPI\CustomForm;
use pocketmine\command\{Command, CommandSender};
use jojoe77777\FormAPI\SimpleForm;
use pocketmine\event\Listener;
use pocketmine\event\player\PlayerChatEvent;
use pocketmine\network\mcpe\protocol\PlaySoundPacket;
use pocketmine\Player;
use pocketmine\plugin\PluginBase;
use pocketmine\utils\Config;
use pocketmine\utils\TextFormat;

class MentionSound extends PluginBase implements Listener
{
	/** @var array */
	private $soundList = [];

	public function onEnable()
	{
		$this->saveResource("soundlist.yml");
		$soundConfig = new Config($this->getDataFolder() . "soundlist.yml", Config::YAML);
		$this->soundList = $soundConfig->getAll();
		$this->getServer()->getPluginManager()->registerEvents($this, $this);
	}

	public function onCommand(CommandSender $sender, Command $command, string $label, array $args): bool
	{
		if (strtolower($command->getName()) === "mentionsound") {
			if (!($sender instanceof Player)) return false;
			/** @var Player $player */
			$player = $sender;
			if (isset($args[0])) {
				if (strtolower($args[0]) === "settings") {
					$this->settingsForm($player);
					return true;
				} elseif (strtolower($args[0]) === "soundlist") {
					$this->soundListForm($player);
					return true;
				}
			}
			$this->helpForm($player);
		}
		return true;
	}

	public function helpForm(Player $player)
	{
		$form = new SimpleForm(function (Player $player, $data = null) {
			if ($data === null) return;
			if ($data === "settings") $this->settingsForm($player);
			elseif ($data === "soundList") $this->soundListForm($player);
		});
		$form->setTitle("MentionSound");
		$form->addButton("Settings", 0, "textures/ui/settings_glyph_color_2x", "settings");
		$form->addButton("Sound List", 0, "textures/ui/sound_glyph_2x", "soundList");
		$player->sendForm($form);
	}

	private function soundListForm(Player $player)
	{
		$form = new SimpleForm(function () {
		});
		$form->setTitle("Sound List");
		$form->setContent(implode("\n", $this->soundList));
		$player->sendForm($form);
	}

	private function settingsForm(Player $player)
	{
		$userSettings = $this->getUserSettings($player->getName());
		$form = new CustomForm(function (Player $player, $data = null) use ($userSettings) {
			if (!is_array($data)) return;
			if (trim($data["nameMentionSound"]) !== "" and !in_array(trim($data["nameMentionSound"]), $this->soundList)) {
				$player->sendMessage(TextFormat::RED . "Sound {$data["nameMentionSound"]} not found");
				return;
			}
			if (trim($data["hereMentionSound"]) !== "" and !in_array($data["hereMentionSound"], $this->soundList)) {
				$player->sendMessage(TextFormat::RED . "Sound {$data["hereMentionSound"]} not found");
				return;
			}
			if (!is_numeric($data["pitch"])) {
				$player->sendMessage(TextFormat::RED . "Pitch must be a number");
				return;
			}
			$nameMentionSound = trim($data["nameMentionSound"]) === "" ? $userSettings->get("nameMentionSound") : $data["nameMentionSound"];
			$hereMentionSound = trim($data["hereMentionSound"]) === "" ? $userSettings->get("hereMentionSound") : $data["hereMentionSound"];
			$this->setUserSettings($player->getName(), $data["nameMention"], $data["hereMention"], $nameMentionSound, $hereMentionSound, (float) $data["pitch"]);
			$player->sendMessage(TextFormat::GREEN . "Your Settings was successfully saved");
		});
		$form->setTitle("MentionSound Settings");
		$form->addToggle("@{$player->getDisplayName()} Mention", $userSettings->get("nameMention"), "nameMention");
		$form->addInput("@{$player->getDisplayName()} Mention Sound", $userSettings->get("nameMentionSound"), $userSettings->get("nameMentionSound"), "nameMentionSound");
		$form->addToggle("@here Mention", $userSettings->get("hereMention"), "hereMention");
		$form->addInput("@here Mention Sound", $userSettings->get("hereMentionSound"), $userSettings->get("hereMentionSound"), "hereMentionSound");
		$form->addInput("Pitch", $userSettings->get("pitch"), $userSettings->get("pitch"), "pitch");
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
	 * @ignoreCancelled true
	 */
	public function onChat(PlayerChatEvent $event): void
	{
		if (strpos($event->getMessage(), "@") !== false) {
			foreach ($this->getServer()->getOnlinePlayers() as $player) {
				$userSettings = $this->getUserSettings($player->getName());
				$pk = new PlaySoundPacket();
				$pk->x = $player->x;
				$pk->y = $player->y;
				$pk->z = $player->z;
				$pk->volume = 1.0;
				$pk->pitch = (float) $userSettings->get("pitch");
				if (strpos($event->getMessage(), "@{$player->getDisplayName()}") !== false and $userSettings->get("nameMention")) {
					$pk->soundName = (string) $userSettings->get("nameMentionSound");
					$player->dataPacket($pk);
				}
				elseif (strpos($event->getMessage(), "@here") !== false and $userSettings->get("hereMention")) {
					$pk->soundName = (string) $userSettings->get("hereMentionSound");
					$player->dataPacket($pk);
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