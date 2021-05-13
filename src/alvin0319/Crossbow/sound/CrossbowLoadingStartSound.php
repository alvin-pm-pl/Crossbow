<?php

declare(strict_types=1);

namespace alvin0319\Crossbow\sound;

use pocketmine\level\sound\GenericSound;
use pocketmine\math\Vector3;
use pocketmine\network\mcpe\protocol\LevelSoundEventPacket;

final class CrossbowLoadingStartSound extends GenericSound{

	protected bool $quickCharge = false;

	public function __construct(Vector3 $pos, bool $quickCharge = false){
		parent::__construct($pos, -1);
		$this->quickCharge = $quickCharge;
	}

	public function encode(){
		$pk = new LevelSoundEventPacket();
		$pk->sound = $this->quickCharge ? LevelSoundEventPacket::SOUND_CROSSBOW_QUICK_CHARGE_START : LevelSoundEventPacket::SOUND_CROSSBOW_LOADING_START;
		$pk->position = $this->floor();
		return $pk;
	}
}