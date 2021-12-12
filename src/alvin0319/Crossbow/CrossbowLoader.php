<?php

declare(strict_types=1);

namespace alvin0319\Crossbow;

use alvin0319\Crossbow\item\Crossbow;
use pocketmine\data\bedrock\EnchantmentIdMap;
use pocketmine\data\bedrock\EnchantmentIds;
use pocketmine\event\Listener;
use pocketmine\event\player\PlayerItemUseEvent;
use pocketmine\event\server\DataPacketReceiveEvent;
use pocketmine\inventory\CreativeInventory;
use pocketmine\item\enchantment\Enchantment;
use pocketmine\item\enchantment\ItemFlags;
use pocketmine\item\enchantment\Rarity;
use pocketmine\item\enchantment\StringToEnchantmentParser;
use pocketmine\item\ItemFactory;
use pocketmine\item\ItemIdentifier;
use pocketmine\item\ItemIds;
use pocketmine\item\ItemUseResult;
use pocketmine\network\mcpe\convert\TypeConverter;
use pocketmine\network\mcpe\protocol\InventoryTransactionPacket;
use pocketmine\network\mcpe\protocol\types\inventory\ReleaseItemTransactionData;
use pocketmine\network\mcpe\protocol\types\inventory\UseItemTransactionData;
use pocketmine\plugin\PluginBase;
use pocketmine\scheduler\ClosureTask;
use pocketmine\utils\AssumptionFailedError;
use function var_dump;

final class CrossbowLoader extends PluginBase implements Listener{

	public static array $crossbowLoadData = [];

	public function onEnable() : void{
		$this->getServer()->getPluginManager()->registerEvents($this, $this);

		ItemFactory::getInstance()->register($crossbow = new Crossbow(new ItemIdentifier(ItemIds::CROSSBOW, 0), "Crossbow"), true);

		CreativeInventory::getInstance()->add($crossbow);

		$enchMap = EnchantmentIdMap::getInstance();

		$enchMap->register(EnchantmentIds::MULTISHOT, $multishot = new Enchantment("Multishot", Rarity::MYTHIC, ItemFlags::BOW, ItemFlags::NONE, 1));
		$enchMap->register(EnchantmentIds::QUICK_CHARGE, $quickCharge = new Enchantment("Quick charge", Rarity::MYTHIC, ItemFlags::BOW, ItemFlags::NONE, 3));

		StringToEnchantmentParser::getInstance()->register("multishot", fn() => $multishot);
		StringToEnchantmentParser::getInstance()->register("quick_charge", fn() => $quickCharge);

		$this->getScheduler()->scheduleRepeatingTask(new ClosureTask(function() use ($enchMap) : void{
			foreach(self::$crossbowLoadData as $name => $bool){
				$player = $this->getServer()->getPlayerExact($name);
				if($player !== null){
					$itemInHand = $player->getInventory()->getItemInHand();
					if($itemInHand instanceof Crossbow){
						$quickCharge = $itemInHand->getEnchantmentLevel($enchMap->fromId(EnchantmentIds::QUICK_CHARGE));
						$time = $player->getItemUseDuration();
						if($time >= 24 - $quickCharge * 5){
							$itemInHand->onReleaseUsing($player);
							$player->getInventory()->setItemInHand($itemInHand);
							unset(self::$crossbowLoadData[$name]);
						}
					}else{
						unset(self::$crossbowLoadData[$name]);
					}
				}else{
					unset(self::$crossbowLoadData[$name]);
				}
			}
		}), 1);
	}

	/**
	 * @param DataPacketReceiveEvent $event
	 *
	 * @handleCancelled true
	 */
	public function onDataPacketReceive(DataPacketReceiveEvent $event) : void{
		$packet = $event->getPacket();
		if(!$packet instanceof InventoryTransactionPacket){
			return;
		}
		$player = $event->getOrigin()->getPlayer() ?: throw new AssumptionFailedError("Player is not online");
		$trData = $packet->trData;
		$conv = TypeConverter::getInstance();
		switch(true){
			case $trData instanceof UseItemTransactionData:
				$item = $conv->netItemStackToCore($trData->getItemInHand()->getItemStack());
				if($item instanceof Crossbow){
					$event->cancel();
					$oldItem = clone $item;
					$ev = new PlayerItemUseEvent($player, $item, $player->getDirectionVector());
					if($player->hasItemCooldown($item) || $player->isSpectator()){
						$ev->cancel();
					}
					$ev->call();
					if($ev->isCancelled()){
						var_dump("???");
						return;
					}
					if($item->onClickAir($player, $player->getDirectionVector())->equals(ItemUseResult::FAIL())){
						var_dump("?");
						$player->getNetworkSession()->getInvManager()?->syncSlot($player->getInventory(), $player->getInventory()->getHeldItemIndex());
						return;
					}
					$player->resetItemCooldown($item);
					if(!$item->equalsExact($oldItem) && $oldItem->equalsExact($player->getInventory()->getItemInHand())){
						$player->getInventory()->setItemInHand($item);
					}
					if(!$oldItem->isCharged() && !$item->isCharged()){
						$player->setUsingItem(true);
					}else{
						$player->setUsingItem(false);
					}
				}
				break;
			case $trData instanceof ReleaseItemTransactionData:
				$item = $player->getInventory()->getItemInHand();
				if($item instanceof Crossbow){
					$event->cancel();
					if(!$player->isUsingItem() || $player->hasItemCooldown($item)){
						return;
					}
					if($item->onReleaseUsing($player)->equals(ItemUseResult::SUCCESS())){
						$player->resetItemCooldown($item);
						$player->getInventory()->setItemInHand($item);
					}
				}
				break;
		}
	}
}