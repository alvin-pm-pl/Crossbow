<?php

declare(strict_types=1);

namespace alvin0319\Crossbow\sound;

use pocketmine\level\sound\GenericSound;
use pocketmine\math\Vector3;
use pocketmine\network\mcpe\protocol\LevelSoundEventPacket;

final class CrossbowShootSound extends GenericSound{

	public function __construct(Vector3 $pos){
		parent::__construct($pos, -1);
	}

	public function encode(){
		$pk = new LevelSoundEventPacket();
		$pk->sound = LevelSoundEventPacket::SOUND_CROSSBOW_SHOOT;
		$pk->position = $this->floor();
		return $pk;
	}
}