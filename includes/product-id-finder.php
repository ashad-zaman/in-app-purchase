<?php
class Product_Id_Finder
{
    /**
     * @var float[]
     */
    private array $prices;

    /**
     * @param float[] $prices
     */
    public function __construct(array $prices)
    {
        $prices = $this->getApplePriceList();
        sort($prices, SORT_NUMERIC);
        $this->prices = array_values(array_unique($prices));
    }

    /**
     * Summary of getApplePriceList
     * @return float[]
     */
    private function getApplePriceList() {

        $priceList = [
                        0, 0.29, 0.39, 0.49, 0.59, 0.69, 0.79, 0.89, 0.9, 0.95, 0.99, 1,
                        1.09, 1.19, 1.29, 1.39, 1.49, 1.59, 1.69, 1.79, 1.89, 1.9, 1.95, 1.99, 2,
                        2.09, 2.19, 2.29, 2.39, 2.49, 2.59, 2.69, 2.79, 2.89, 2.9, 2.95, 2.99, 3,
                        3.09, 3.19, 3.29, 3.39, 3.49, 3.59, 3.69, 3.79, 3.89, 3.9, 3.95, 3.99, 4,
                        4.09, 4.19, 4.29, 4.39, 4.49, 4.59, 4.69, 4.79, 4.89, 4.9, 4.95, 4.99, 5,
                        5.09, 5.19, 5.29, 5.39, 5.49, 5.59, 5.69, 5.79, 5.89, 5.9, 5.95, 5.99, 6,
                        6.09, 6.19, 6.29, 6.39, 6.49, 6.59, 6.69, 6.79, 6.89, 6.9, 6.95, 6.99, 7,
                        7.09, 7.19, 7.29, 7.39, 7.49, 7.59, 7.69, 7.79, 7.89, 7.9, 7.95, 7.99, 8,
                        8.09, 8.19, 8.29, 8.39, 8.49, 8.59, 8.69, 8.79, 8.89, 8.9, 8.95, 8.99, 9,
                        9.09, 9.19, 9.29, 9.39, 9.49, 9.59, 9.69, 9.79, 9.89, 9.9, 9.95, 9.99, 10,
                        10.49, 10.9, 10.95, 10.99, 11, 11.49, 11.9, 11.95, 11.99, 12,
                        12.49, 12.9, 12.95, 12.99, 13, 13.49, 13.9, 13.95, 13.99, 14,
                        14.49, 14.9, 14.95, 14.99, 15, 15.49, 15.9, 15.95, 15.99, 16,
                        16.49, 16.9, 16.95, 16.99, 17, 17.49, 17.9, 17.95, 17.99, 18,
                        18.49, 18.9, 18.95, 18.99, 19, 19.49, 19.9, 19.95, 19.99, 20,
                        20.49, 20.9, 20.95, 20.99, 21, 21.49, 21.9, 21.95, 21.99, 22,
                        22.49, 22.9, 22.95, 22.99, 23, 23.49, 23.9, 23.95, 23.99, 24,
                        24.49, 24.9, 24.95, 24.99, 25, 25.49, 25.9, 25.95, 25.99, 26,
                        26.49, 26.9, 26.95, 26.99, 27, 27.49, 27.9, 27.95, 27.99, 28,
                        28.49, 28.9, 28.95, 28.99, 29, 29.49, 29.9, 29.95, 29.99, 30,
                        30.49, 30.9, 30.95, 30.99, 31, 31.49, 31.9, 31.95, 31.99, 32,
                        32.49, 32.9, 32.95, 32.99, 33, 33.49, 33.9, 33.95, 33.99, 34,
                        34.49, 34.9, 34.95, 34.99, 35, 35.49, 35.9, 35.95, 35.99, 36,
                        36.49, 36.9, 36.95, 36.99, 37, 37.49, 37.9, 37.95, 37.99, 38,
                        38.49, 38.9, 38.95, 38.99, 39, 39.49, 39.9, 39.95, 39.99, 40,
                        40.49, 40.9, 40.95, 40.99, 41, 41.49, 41.9, 41.95, 41.99, 42,
                        42.49, 42.9, 42.95, 42.99, 43, 43.49, 43.9, 43.95, 43.99, 44,
                        44.49, 44.9, 44.95, 44.99, 45, 45.49, 45.9, 45.95, 45.99, 46,
                        46.49, 46.9, 46.95, 46.99, 47, 47.49, 47.9, 47.95, 47.99, 48,
                        48.49, 48.9, 48.95, 48.99, 49, 49.49, 49.9, 49.95, 49.99, 50,
                    ];

        return $priceList;

    }

    /**
     * Get 4 nearest prices (2 below and 2 above) for the given real price.
     *
     * @param float $realPrice
     * @return float[]
     */
    public function getNearestPrices(float $realPrice): array
    {
        $index = $this->findNearestIndex($realPrice);

        // Collect two below and two above
        $result = [];

        for ($i = $index - 2; $i <= $index + 2; $i++) {
            if ($i >= 0 && $i < count($this->prices)) {
                $result[] = $this->prices[$i];
            }
        }

        // Ensure max 4 items (2 below, 2 above) excluding the closest itself if needed
        // We pick the 4 closest by distance
        usort($result, fn($a, $b) => abs($a - $realPrice) <=> abs($b - $realPrice));

        $result = array_slice($result, 0, 4);

        // Ensure ascending order
        sort($result, SORT_NUMERIC);

        return $result;
    }

    /**
     * Find the index of the nearest price to the given value.
     *
     * @param float $realPrice
     * @return int
     */
    private function findNearestIndex(float $realPrice): int
    {
        $low = 0;
        $high = count($this->prices) - 1;

        while ($low <= $high) {
            $mid = intdiv($low + $high, 2);
            if ($this->prices[$mid] < $realPrice) {
                $low = $mid + 1;
            } elseif ($this->prices[$mid] > $realPrice) {
                $high = $mid - 1;
            } else {
                return $mid; // exact match
            }
        }

        // $low is the insertion point
        if ($low >= count($this->prices)) {
            return count($this->prices) - 1;
        }
        if ($low === 0) {
            return 0;
        }

        // Pick the closer between low and low-1
        return (abs($this->prices[$low] - $realPrice) < abs($this->prices[$low - 1] - $realPrice))
            ? $low
            : $low - 1;
    }
}
 