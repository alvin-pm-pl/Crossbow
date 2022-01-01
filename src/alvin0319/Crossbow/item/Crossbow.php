<?php

declare(strict_types=1);

namespace alvin0319\Crossbow\item;

use alvin0319\Crossbow\CrossbowLoader;
use alvin0319\Crossbow\event\EntityShootCrossbowEvent;
use alvin0319\Crossbow\sound\CrossbowLoadingEndSound;
use alvin0319\Crossbow\sound\CrossbowLoadingStartSound;
use alvin0319\Crossbow\sound\CrossbowShootSound;
use pocketmine\data\bedrock\EnchantmentIdMap;
use pocketmine\data\bedrock\EnchantmentIds;
use pocketmine\entity\Location;
use pocketmine\entity\projectile\Arrow as ArrowEntity;
use pocketmine\entity\projectile\Projectile;
use pocketmine\event\entity\ProjectileLaunchEvent;
use pocketmine\item\Arrow as ArrowItem;
use pocketmine\item\Item;
use pocketmine\item\ItemUseResult;
use pocketmine\item\Tool;
use pocketmine\item\VanillaItems;
use pocketmine\math\Vector3;
use pocketmine\player\Player;
use function cos;
use function deg2rad;
use function sin;

final class Crossbow extends Tool{

	public function onClickAir(Player $player, Vector3 $directionVector) : ItemUseResult{
		$arrow = VanillaItems::ARROW()
			->setCount(1);
		$enchIdMap = EnchantmentIdMap::getInstance();
		$quickCharge = $this->getEnchantmentLevel($enchIdMap->fromId(EnchantmentIds::QUICK_CHARGE));
		$multishot = $this->getEnchantmentLevel($enchIdMap->fromId(EnchantmentIds::MULTISHOT));
		$location = $player->getLocation();
		if(!$this->isCharged()){
			if($player->isSurvival() && !($player->getInventory()->contains($arrow))){
				return ItemUseResult::FAIL();
			}
			$player->getWorld()->addSound($location, new CrossbowLoadingStartSound($quickCharge > 0));
			CrossbowLoader::$crossbowLoadData[$player->getName()] = true;
		}else{
			$item = Item::nbtDeserialize($this->getNamedTag()->getCompoundTag("chargedItem"));
			$this->setCharged(null);
			if($item instanceof ArrowItem){
				//$nbt = Entity::createBaseNBT($player->getDirectionVector()->multiply(1.3)->add($player->add(0, $player->getEyeHeight())), $directionVector, ($location->yaw > 180 ? 360 : 0) - $location->yaw, -$location->pitch);
				///** @var ArrowEntity $entity */
				//$entity = Entity::createEntity("Arrow", $player->getLevel(), $nbt);
				//$entity->setOwningEntity($player);

				$entity = new ArrowEntity(Location::fromObject($player->getDirectionVector()->multiply(1.3)->addVector($player->getPosition()->add(0, $player->getEyeHeight(), 0)), $player->getWorld(), ($location->yaw > 180 ? 360 : 0) - $location->yaw, -$location->pitch), $player, false);

				if($multishot > 0){
					$location = Location::fromObject($player->getDirectionVector()->multiply(1.3)->addVector($player->getPosition()->add(0, $player->getEyeHeight(), 0)), $player->getWorld(), $player->getLocation()->getYaw(), $player->getLocation()->getPitch());
					$location->yaw -= 10;

					for($i = 0; $i < 3; $i++){
						$arrow = new ArrowEntity($location, $player, false);

						$arrow->setOwningEntity($player);

						if($i !== 1 || $player->isCreative(true)){
							$arrow->setPickupMode(ArrowEntity::PICKUP_CREATIVE);
						}

						$y = -sin(deg2rad($location->pitch));
						$xz = cos(deg2rad($location->pitch));
						$x = -$xz * sin(deg2rad($location->yaw));
						$z = $xz * cos(deg2rad($location->yaw));

						$directionVector = (new Vector3($x, $y, $z))->normalize();

						$arrow->setMotion($directionVector->multiply(7));

						$arrow->spawnToAll();

						$location->yaw += 10;
					}
					if($player->isSurvival()){
						$this->applyDamage($multishot ? 3 : 1);
					}

					$location->getWorld()->addSound($location, new CrossbowShootSound());

					return ItemUseResult::SUCCESS();
				}
				$entity->setMotion($directionVector);

				$ev = new EntityShootCrossbowEvent($player, $this, $entity, 7);
				$ev->call();

				$entity = $ev->getProjectile();

				if($ev->isCancelled()){
					$entity->flagForDespawn();
					return ItemUseResult::FAIL();
				}

				$entity->setMotion($entity->getMotion()->multiply($ev->getForce()));

				if($entity instanceof Projectile){
					$projectileEv = new ProjectileLaunchEvent($entity);
					$projectileEv->call();
					if($projectileEv->isCancelled()){
						$ev->getProjectile()->flagForDespawn();
						return ItemUseResult::FAIL();
					}

					$ev->getProjectile()->spawnToAll();
					$location->getWorld()->addSound($location, new CrossbowShootSound());
				}else{
					$entity->spawnToAll();
				}

				if($player->isSurvival()){
					$this->applyDamage($multishot ? 3 : 1);
				}
			}else{
				return ItemUseResult::SUCCESS();
			}
		}
		return ItemUseResult::SUCCESS();
	}

	public function onReleaseUsing(Player $player) : ItemUseResult{
		unset(CrossbowLoader::$crossbowLoadData[$player->getName()]);
		$arrow = VanillaItems::ARROW()->setCount(1);
		$quickCharge = $this->getEnchantmentLevel(EnchantmentIdMap::getInstance()->fromId(EnchantmentIds::QUICK_CHARGE));
		$time = $player->getItemUseDuration();
		if($time >= 24 - $quickCharge * 5){
			if($player->isSurvival() && $player->getInventory()->contains($arrow)){
				$player->getInventory()->removeItem($arrow);
			}
			$this->setCharged($arrow);
			$player->getWorld()->addSound($player->getLocation(), new CrossbowLoadingEndSound($quickCharge > 0));
			return ItemUseResult::SUCCESS();
		}
		return ItemUseResult::FAIL();
	}

	public function getMaxDurability() : int{
		return 464;
	}

	public function isCharged() : bool{
		return $this->getNamedTag()->getCompoundTag("chargedItem") !== null;
	}

	public function setCharged(?Item $item) : void{
		if($item === null){
			$this->getNamedTag()->removeTag("chargedItem");
		}else{
			$this->getNamedTag()->setTag("chargedItem", $item->nbtSerialize(-1));
		}
	}
}