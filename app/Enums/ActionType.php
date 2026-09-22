<?php

namespace App\Enums;

enum ActionType: string
{
    case StampAdded = 'stamp_added';
    case RewardRedeemed = 'reward_redeemed';
}
