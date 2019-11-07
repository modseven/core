<?php
/**
 * Provides simple benchmarking and profiling. To display the statistics that
 * have been collected, load the `profiler/stats` [View]:
 *
 *     echo View::factory('profiler/stats');
 *
 * @package    Modseven
 * @category   Helpers
 *
 * @copyright  (c) 2007-2016  Kohana Team
 * @copyright  (c) 2016-2019  Koseven Team
 * @copyright  (c) since 2019 Modseven Team
 * @license    https://koseven.ga/LICENSE
 */

namespace Modseven;

class Profiler
{
    /**
     * maximum number of application stats to keep
     * @var integer
     */
    public static int $rollover = 1000;

    /**
     * collected benchmarks
     * @var  array
     */
    protected static array $_marks = [];

    /**
     * Starts a new benchmark and returns a unique token. The returned token
     * _must_ be used when stopping the benchmark.
     *
     * @param string $group group name
     * @param string $name benchmark name
     * @return  string
     */
    public static function start(string $group, string $name): string
    {
        static $counter = 0;

        // Create a unique token based on the counter
        $token = 'kp/' . base_convert($counter++, 10, 32);

        static::$_marks[$token] = [
            'group' => strtolower($group),
            'name' => $name,

            // Start the benchmark
            'start_time' => microtime(TRUE),
            'start_memory' => memory_get_usage(),

            // Set the stop keys without values
            'stop_time' => FALSE,
            'stop_memory' => FALSE,
        ];

        return $token;
    }

    /**
     * Stops a benchmark.
     *
     * @param string $token
     * @return  void
     */
    public static function stop(string $token): void
    {
        // Stop the benchmark
        static::$_marks[$token]['stop_time'] = microtime(TRUE);
        static::$_marks[$token]['stop_memory'] = memory_get_usage();
    }

    /**
     * Deletes a benchmark. If an error occurs during the benchmark, it is
     * recommended to delete the benchmark to prevent statistics from being
     * adversely affected.
     *
     * @param string $token
     * @return  void
     */
    public static function delete(string $token): void
    {
        // Remove the benchmark
        unset(static::$_marks[$token]);
    }

    /**
     * Gets the min, max, average and total of profiler groups as an array.
     *
     * @param mixed $groups single group name string, or array with group names; all groups by default
     * @return  array   min, max, average, total
     */
    public static function groupStats($groups = NULL): array
    {
        // Which groups do we need to calculate stats for?
        $groups = ($groups === NULL)
            ? self::groups()
            : array_intersect_key(self::groups(), array_flip((array)$groups));

        // All statistics
        $stats = [];

        foreach ($groups as $group => $names) {
            foreach ($names as $name => $tokens) {
                // Store the stats for each subgroup.
                // We only need the values for "total".
                $_stats = self::stats($tokens);
                $stats[$group][$name] = $_stats['total'];
            }
        }

        // Group stats
        $groups = [];

        foreach ($stats as $group => $names) {
            // Min and max are unknown by default
            $groups[$group]['min'] = $groups[$group]['max'] = [
                'time' => NULL,
                'memory' => NULL];

            // Total values are always integers
            $groups[$group]['total'] = [
                'time' => 0,
                'memory' => 0];

            foreach ($names as $total) {
                if (!isset($groups[$group]['min']['time']) || $groups[$group]['min']['time'] > $total['time']) {
                    // Set the minimum time
                    $groups[$group]['min']['time'] = $total['time'];
                }
                if (!isset($groups[$group]['min']['memory']) || $groups[$group]['min']['memory'] > $total['memory']) {
                    // Set the minimum memory
                    $groups[$group]['min']['memory'] = $total['memory'];
                }

                if (!isset($groups[$group]['max']['time']) || $groups[$group]['max']['time'] < $total['time']) {
                    // Set the maximum time
                    $groups[$group]['max']['time'] = $total['time'];
                }
                if (!isset($groups[$group]['max']['memory']) || $groups[$group]['max']['memory'] < $total['memory']) {
                    // Set the maximum memory
                    $groups[$group]['max']['memory'] = $total['memory'];
                }

                // Increase the total time and memory
                $groups[$group]['total']['time'] += $total['time'];
                $groups[$group]['total']['memory'] += $total['memory'];
            }

            // Determine the number of names (subgroups)
            $count = count($names);

            // Determine the averages
            $groups[$group]['average']['time'] = $groups[$group]['total']['time'] / $count;
            $groups[$group]['average']['memory'] = $groups[$group]['total']['memory'] / $count;
        }

        return $groups;
    }

    /**
     * Returns all the benchmark tokens by group and name as an array.
     *
     * @return  array
     */
    public static function groups(): array
    {
        $groups = [];

        foreach (static::$_marks as $token => $mark) {
            // Sort the tokens by the group and name
            $groups[$mark['group']][$mark['name']][] = $token;
        }

        return $groups;
    }

    /**
     * Gets the min, max, average and total of a set of tokens as an array.
     *
     * @param array $tokens profiler tokens
     * @return  array   min, max, average, total
     */
    public static function stats(array $tokens): array
    {
        // Min and max are unknown by default
        $min = $max = [
            'time' => NULL,
            'memory' => NULL];

        // Total values are always integers
        $total = [
            'time' => 0,
            'memory' => 0];

        foreach ($tokens as $token) {
            // Get the total time and memory for this benchmark
            [$time, $memory] = self::total($token);

            if ($max['time'] === NULL || $time > $max['time']) {
                // Set the maximum time
                $max['time'] = $time;
            }

            if ($min['time'] === NULL || $time < $min['time']) {
                // Set the minimum time
                $min['time'] = $time;
            }

            // Increase the total time
            $total['time'] += $time;

            if ($max['memory'] === NULL || $memory > $max['memory']) {
                // Set the maximum memory
                $max['memory'] = $memory;
            }

            if ($min['memory'] === NULL || $memory < $min['memory']) {
                // Set the minimum memory
                $min['memory'] = $memory;
            }

            // Increase the total memory
            $total['memory'] += $memory;
        }

        // Determine the number of tokens
        $count = count($tokens);

        // Determine the averages
        $average = [
            'time' => $total['time'] / $count,
            'memory' => $total['memory'] / $count];

        return [
            'min' => $min,
            'max' => $max,
            'total' => $total,
            'average' => $average];
    }

    /**
     * Gets the total execution time and memory usage of a benchmark as a list.
     *
     * @param string $token
     * @return  array   execution time, memory
     */
    public static function total(string $token): array
    {
        // Import the benchmark data
        $mark = static::$_marks[$token];

        if ($mark['stop_time'] === FALSE) {
            // The benchmark has not been stopped yet
            $mark['stop_time'] = microtime(TRUE);
            $mark['stop_memory'] = memory_get_usage();
        }

        return [
            // Total time in seconds
            $mark['stop_time'] - $mark['start_time'],

            // Amount of memory in bytes
            $mark['stop_memory'] - $mark['start_memory'],
        ];
    }

    /**
     * Gets the total application run time and memory usage. Caches the result
     * so that it can be compared between requests.
     *
     * @return  array  execution time, memory
     *
     * @throws Exception
     */
    public static function application(): array
    {
        // Load the stats from cache, which is valid for 1 day
        $stats = Core::cache('profiler_application_stats', NULL, 3600 * 24);

        if (!is_array($stats) || $stats['count'] > static::$rollover) {
            // Initialize the stats array
            $stats = [
                'min' => [
                    'time' => NULL,
                    'memory' => NULL],
                'max' => [
                    'time' => NULL,
                    'memory' => NULL],
                'total' => [
                    'time' => NULL,
                    'memory' => NULL],
                'count' => 0];
        }

        // Get the application run time
        $time = microtime(TRUE) - MODSEVEN_START_TIME;

        // Get the total memory usage
        $memory = memory_get_usage() - MODSEVEN_START_MEMORY;

        // Calculate max time
        if ($stats['max']['time'] === NULL || $time > $stats['max']['time']) {
            $stats['max']['time'] = $time;
        }

        // Calculate min time
        if ($stats['min']['time'] === NULL || $time < $stats['min']['time']) {
            $stats['min']['time'] = $time;
        }

        // Add to total time
        $stats['total']['time'] += $time;

        // Calculate max memory
        if ($stats['max']['memory'] === NULL || $memory > $stats['max']['memory']) {
            $stats['max']['memory'] = $memory;
        }

        // Calculate min memory
        if ($stats['min']['memory'] === NULL || $memory < $stats['min']['memory']) {
            $stats['min']['memory'] = $memory;
        }

        // Add to total memory
        $stats['total']['memory'] += $memory;

        // Another mark has been added to the stats
        $stats['count']++;

        // Determine the averages
        $stats['average'] = [
            'time' => $stats['total']['time'] / $stats['count'],
            'memory' => $stats['total']['memory'] / $stats['count']];

        // Cache the new stats
        Core::cache('profiler_application_stats', $stats);

        // Set the current application execution time and memory
        // Do NOT cache these, they are specific to the current request only
        $stats['current']['time'] = $time;
        $stats['current']['memory'] = $memory;

        // Return the total application run time and memory usage
        return $stats;
    }

}
