<?php

declare(strict_types=1);

namespace alvin0319\Crossbow\item;

use alvin0319\Crossbow\CrossbowLoader;
use alvin0319\Crossbow\event\EntityShootCrossbowEvent;
use alvin0319\Crossbow\sound\CrossbowLoadingEndSound;
use alvin0319\Crossbow\sound\CrossbowLoadingStartSound;
use alvin0319\Crossbow\sound\CrossbowShootSound;
use pocketmine\entity\Entity;
use pocketmine\entity\projectile\Arrow as ArrowEntity;
use pocketmine\entity\projectile\Projectile;
use pocketmine\event\entity\ProjectileLaunchEvent;
use pocketmine\item\Arrow as ArrowItem;
use pocketmine\item\enchantment\Enchantment;
use pocketmine\item\Item;
use pocketmine\item\ItemFactory;
use pocketmine\item\ItemIds;
use pocketmine\item\Tool;
use pocketmine\level\Location;
use pocketmine\math\Vector3;
use pocketmine\Player;
use function cos;
use function deg2rad;
use function sin;

final class Crossbow extends Tool{

	public function onClickAir(Player $player, Vector3 $directionVector) : bool{
		$arrow = ItemFactory::get(ItemIds::ARROW);
		$quickCharge = $this->getEnchantmentLevel(Enchantment::QUICK_CHARGE);
		$multishot = $this->getEnchantmentLevel(Enchantment::MULTISHOT);
		$location = $player->getLocation();
		if(!$this->isCharged()){
			if($player->isSurvival() && !($player->getInventory()->contains($arrow))){
				return false;
			}
			$player->getLevel()->addSound(new CrossbowLoadingStartSound($location, $quickCharge > 0));
			CrossbowLoader::$crossbowLoadData[$player->getName()] = true;
		}else{
			$item = Item::nbtDeserialize($this->getNamedTag()->getCompoundTag("chargedItem"));
			$this->setCharged(null);
			if($item instanceof ArrowItem){
				$nbt = Entity::createBaseNBT($player->getDirectionVector()->multiply(1.3)->add($player->add(0, $player->getEyeHeight())), $directionVector, ($location->yaw > 180 ? 360 : 0) - $location->yaw, -$location->pitch);
				/** @var ArrowEntity $entity */
				$entity = Entity::createEntity("Arrow", $player->getLevel(), $nbt);
				$entity->setOwningEntity($player);

				if($multishot > 0){
					$location = Location::fromObject($player->getDirectionVector()->multiply(1.3)->add($player->add(0, $player->getEyeHeight())), $player->getLevel(), $player->getYaw(), $player->getPitch());
					$location->yaw -= 10;

					for($i = 0; $i < 3; $i++){
						/** @var ArrowEntity $arrow */
						$arrow = Entity::createEntity("Arrow", $player->getLevel(), $nbt);

						$arrow->setOwningEntity($player);

						if($player->isCreative(true) || $i !== 1){
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

					$location->getLevel()->addSound(new CrossbowShootSound($location));

					return true;
				}
				$entity->setMotion($directionVector);

				$ev = new EntityShootCrossbowEvent($player, $this, $entity, 7);
				$ev->call();

				$entity = $ev->getProjectile();

				if($ev->isCancelled()){
					$entity->flagForDespawn();
					return false;
				}

				$entity->setMotion($entity->getMotion()->multiply($ev->getForce()));

				if($entity instanceof Projectile){
					$projectileEv = new ProjectileLaunchEvent($entity);
					$projectileEv->call();
					if($projectileEv->isCancelled()){
						$ev->getProjectile()->flagForDespawn();
						return false;
					}

					$ev->getProjectile()->spawnToAll();
					$location->getLevel()->addSound(new CrossbowShootSound($location));
				}else{
					$entity->spawnToAll();
				}

				if($player->isSurvival()){
					$this->applyDamage($multishot ? 3 : 1);
				}
			}else{
				return true;
			}
		}
		return true;
	}

	public function onReleaseUsing(Player $player) : bool{
		unset(CrossbowLoader::$crossbowLoadData[$player->getName()]);
		$arrow = ItemFactory::get(ItemIds::ARROW);
		$quickCharge = $this->getEnchantmentLevel(Enchantment::QUICK_CHARGE);
		$time = $player->getItemUseDuration();
		if($time >= 24 - $quickCharge * 5){
			if($player->isSurvival()){
				if($player->getInventory()->contains($arrow)){
					$player->getInventory()->removeItem($arrow);
				}
			}
			$this->setCharged($arrow);
			$player->getLevel()->addSound(new CrossbowLoadingEndSound($player->getLocation(), $quickCharge > 0));
			return true;
		}
		return false;
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
			$this->getNamedTag()->setTag($item->nbtSerialize(-1, "chargedItem"));
		}
	}
}