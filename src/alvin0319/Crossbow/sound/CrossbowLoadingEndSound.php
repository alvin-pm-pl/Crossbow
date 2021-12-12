<?php

declare(strict_types=1);

namespace alvin0319\Crossbow\sound;

use pocketmine\math\Vector3;
use pocketmine\network\mcpe\protocol\LevelSoundEventPacket;
use pocketmine\network\mcpe\protocol\types\LevelSoundEvent;
use pocketmine\world\sound\Sound;

final class CrossbowLoadingEndSound implements Sound{

	protected bool $quickCharge = false;

	public function __construct(bool $quickCharge = false){
		$this->quickCharge = $quickCharge;
	}

	public function encode(Vector3 $pos) : array{
		return [
			LevelSoundEventPacket::nonActorSound(
				$this->quickCharge ? LevelSoundEvent::CROSSBOW_QUICK_CHARGE_END : LevelSoundEvent::CROSSBOW_LOADING_END,
				$pos,
				false
			)
		];
	}
}