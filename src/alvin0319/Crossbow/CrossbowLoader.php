<?php

declare(strict_types=1);

namespace alvin0319\Crossbow;

use alvin0319\Crossbow\item\Crossbow;
use pocketmine\event\Listener;
use pocketmine\event\player\PlayerInteractEvent;
use pocketmine\event\server\DataPacketReceiveEvent;
use pocketmine\item\enchantment\Enchantment;
use pocketmine\item\Item;
use pocketmine\item\ItemFactory;
use pocketmine\item\ItemIds;
use pocketmine\network\mcpe\protocol\InventoryTransactionPacket;
use pocketmine\network\mcpe\protocol\types\inventory\ReleaseItemTransactionData;
use pocketmine\network\mcpe\protocol\types\inventory\UseItemTransactionData;
use pocketmine\plugin\PluginBase;
use pocketmine\scheduler\ClosureTask;

final class CrossbowLoader extends PluginBase implements Listener{

	public static array $crossbowLoadData = [];

	public function onEnable() : void{
		$this->getServer()->getPluginManager()->registerEvents($this, $this);

		ItemFactory::registerItem($crossbow = new Crossbow(ItemIds::CROSSBOW, 0, "Crossbow"), true);

		Item::addCreativeItem($crossbow);

		Enchantment::registerEnchantment(new Enchantment(Enchantment::MULTISHOT, "Multishot", Enchantment::RARITY_MYTHIC, Enchantment::SLOT_BOW, Enchantment::SLOT_NONE, 1));
		Enchantment::registerEnchantment(new Enchantment(Enchantment::QUICK_CHARGE, "Quick charge", Enchantment::RARITY_MYTHIC, Enchantment::SLOT_BOW, Enchantment::SLOT_NONE, 3));

		$this->getScheduler()->scheduleRepeatingTask(new ClosureTask(function(int $_) : void{
			foreach(self::$crossbowLoadData as $name => $bool){
				$player = $this->getServer()->getPlayerExact($name);
				if($player !== null){
					$itemInHand = $player->getInventory()->getItemInHand();
					if($itemInHand instanceof Crossbow){
						$quickCharge = $itemInHand->getEnchantmentLevel(Enchantment::QUICK_CHARGE);
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

	public function onDataPacketReceive(DataPacketReceiveEvent $event) : void{
		$packet = $event->getPacket();
		$player = $event->getPlayer();
		if(!$packet instanceof InventoryTransactionPacket){
			return;
		}
		$trData = $packet->trData;
		switch(true){
			case $trData instanceof UseItemTransactionData:
				$item = $trData->getItemInHand()->getItemStack();
				if($item instanceof Crossbow){
					$event->setCancelled();
					$oldItem = clone $item;
					$ev = new PlayerInteractEvent($player, $item, null, $trData->getBlockPos(), $trData->getFace(), PlayerInteractEvent::RIGHT_CLICK_AIR);
					if($player->hasItemCooldown($item) || $player->isSpectator()){
						$ev->setCancelled();
					}
					$ev->call();
					if($ev->isCancelled()){
						return;
					}
					if(!$item->onClickAir($player, $player->getDirectionVector())){
						$player->getInventory()->sendSlot($player->getInventory()->getHeldItemIndex(), [$player]);
						return;
					}
					$player->resetItemCooldown($item);
					if(!$item->equalsExact($oldItem) and $oldItem->equalsExact($player->getInventory()->getItemInHand())){
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
					$event->setCancelled();
					if(!$player->isUsingItem() || $player->hasItemCooldown($item)){
						return;
					}
					if($item->onReleaseUsing($player)){
						$player->resetItemCooldown($item);
						$player->getInventory()->setItemInHand($item);
					}
				}
				break;
		}
	}
}