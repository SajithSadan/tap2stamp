<?php

namespace App\Support;

/**
 * The reward game registry: game types, when a customer gets a play, and
 * the kinds of prize. Each type is drawn by its own component in
 * resources/js/Components/Games/GamePreview.jsx - keep the keys in sync.
 * Adding a game: a TYPES entry + its component.
 */
final class RewardGames
{
    /** `headline`: what customers read above the game unless the owner writes their own. */
    public const TYPES = [
        'wheel' => ['label' => 'Spin the wheel', 'description' => 'Customers spin a prize wheel.', 'headline' => 'Spin to win!', 'min_prizes' => 2, 'max_prizes' => 8],
        'scratch' => ['label' => 'Scratch card', 'description' => 'Customers scratch a card to reveal their prize.', 'headline' => 'Scratch to win!', 'min_prizes' => 2, 'max_prizes' => 8],
        'boxes' => ['label' => 'Mystery boxes', 'description' => 'Customers pick one of three gift boxes.', 'headline' => 'Pick a box to win!', 'min_prizes' => 2, 'max_prizes' => 6],
        'slots' => ['label' => 'Slot machine', 'description' => 'Customers pull the lever; three in a row wins.', 'headline' => 'Pull to win!', 'min_prizes' => 2, 'max_prizes' => 6],
        'gift' => ['label' => 'Gift unwrap', 'description' => 'Customers tap a present to open it.', 'headline' => 'Open your present!', 'min_prizes' => 2, 'max_prizes' => 8],
        'plinko' => ['label' => 'Ball drop', 'description' => 'Customers drop a ball and it lands on a prize.', 'headline' => 'Drop to win!', 'min_prizes' => 2, 'max_prizes' => 7],
    ];

    public const EVERY_N_STAMPS = 'every_n_stamps';

    public const CARD_FULL = 'card_full';

    public const FIRST_STAMP = 'first_stamp';

    public const TRIGGERS = [
        self::EVERY_N_STAMPS => 'Every few stamps',
        self::CARD_FULL => 'When the card is full',
        self::FIRST_STAMP => 'On the first stamp',
    ];

    public const NO_WIN = 'no_win';

    public const BONUS_STAMPS = 'bonus_stamps';

    /** item / discount become a voucher staff give out; bonus stamps are added straight away. */
    public const PRIZE_KINDS = [
        'item' => 'Free item',
        'discount' => 'Discount',
        self::BONUS_STAMPS => 'Bonus stamps',
        self::NO_WIN => 'No win',
    ];

    /** The play limit's period: "3 a day". */
    public const PLAYS_PERIODS = [
        'day' => 'a day',
        'week' => 'a week',
        'month' => 'a month',
        'total' => 'in total',
    ];

    /** What happens once a customer wins a prize (anything but "no win"). */
    public const AFTER_WIN = [
        'continue' => 'Can keep playing',
        'day' => 'Done for the day',
        'game' => 'Done with this game',
    ];

    public const DRAFT = 'draft';

    public const LIVE = 'live';

    public const PAUSED = 'paused';

    public const STATUSES = [self::DRAFT, self::LIVE, self::PAUSED];

    /** Upper limits shared with the builder's inputs. */
    public const MAX_TRIGGER_VALUE = 50;

    public const MAX_BONUS_STAMPS = 5;

    public const MAX_WEIGHT = 1000;

    /** What the owner's forms need (labels and limits). */
    public static function options(): array
    {
        return [
            'types' => collect(self::TYPES)->map(fn ($type, $key) => ['key' => $key, ...$type])->values()->all(),
            'triggers' => collect(self::TRIGGERS)->map(fn ($label, $key) => ['key' => $key, 'label' => $label])->values()->all(),
            'kinds' => collect(self::PRIZE_KINDS)->map(fn ($label, $key) => ['key' => $key, 'label' => $label])->values()->all(),
            'periods' => collect(self::PLAYS_PERIODS)->map(fn ($label, $key) => ['key' => $key, 'label' => $label])->values()->all(),
            'after_win' => collect(self::AFTER_WIN)->map(fn ($label, $key) => ['key' => $key, 'label' => $label])->values()->all(),
            'limits' => [
                'trigger_value' => self::MAX_TRIGGER_VALUE,
                'bonus_stamps' => self::MAX_BONUS_STAMPS,
                'weight' => self::MAX_WEIGHT,
            ],
        ];
    }
}
