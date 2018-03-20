<?php

/**
 *                  _       __ _                _
 *                 | |     / _(_)              | |
 *  _   _ _ __   __| |_ __| |_ _ _ __   ___  __| |
 * | | | | '_ \ / _` | '__|  _| | '_ \ / _ \/ _` |
 * | |_| | | | | (_| | |  | | | | | | |  __/ (_| |
 *  \__,_|_| |_|\__,_|_|  |_| |_|_| |_|\___|\__,_|
 */

namespace VS {


    /**
     * Class ChancesAPI
     * @package VS
     */
    class ChancesAPI
    {


        /**
         * @var array
         */
        private $items = [];

        /**
         * ChancesAPI constructor.
         * @param array $items
         * @throws \Exception
         */
        public function __construct(array $items)
        {
            if (empty($items))
                throw new \Exception();
            foreach ($items as $key => $item)
                $this->items[$key] = $item + (!empty($this->items) ? max($this->items) : 0);
        }

        /**
         * @return int|null|string
         */
        public function next()
        {
            $random = mt_rand(0, max($this->items));
            $last = null;
            foreach ($this->items as $key => $item) {
                if ($last === null)
                    if ($random <= $item)
                        return $key;
                    else
                        $last = $item;
                else
                    if ($random <= $item and $random > $last)
                        return $key;
                    else
                        $last = $item;
            }
        }

    }
}