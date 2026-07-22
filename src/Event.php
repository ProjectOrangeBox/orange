<?php

declare(strict_types=1);

namespace orange\framework;

use Closure;
use orange\framework\base\Singleton;
use orange\framework\exceptions\InvalidValue;
use orange\framework\interfaces\EventInterface;
use orange\framework\traits\ConfigurationTrait;

/**
 * Overview of Event.php
 *
 * This file defines the Event class in the orange\framework namespace.
 * It is the framework’s event manager, responsible for registering, managing, and triggering events across the system.
 * It follows the Singleton pattern (so only one instance is active) and implements the EventInterface.
 * It also uses the ConfigurationTrait to handle event configuration.
 *
 * ⸻
 *
 * 1. Core Purpose
 *  •   Acts as a publish–subscribe system: components can register listeners for named triggers, and those listeners will run when the trigger is fired.
 *  •   Provides priority-based execution so some listeners can run before others.
 *  •   Supports both closures (inline functions) and class–method pairs as event handlers.
 *  •   Centralizes event handling, so code stays loosely coupled and extensible.
 *
 * ⸻
 *
 * 2. Initialization
 *  •   The constructor is protected (Singleton enforced).
 *  •   Accepts a configuration array of event mappings.
 *  •   Uses mergeConfigWith() (from ConfigurationTrait) to combine defaults with provided configuration.
 *  •   Initializes the $events store by looping through config and registering each event.
 *  •   Supports a special disabled flag to globally turn events off.
 *
 * Example configuration format:
 *
 * return [
 *   'before.router' => [
 *       [\app\libraries\OutputCors::class . '::handleCrossOriginResourceSharing', Event::PRIORITY_HIGHEST],
 *       [\app\libraries\Middleware::class . '::beforeRouter'],
 *   ],
 *   'after.controller' => [
 *       [\app\libraries\Middleware::class . '::afterController'],
 *   ],
 * ];
 *
 * 3. Key Features
 *  1.  Enable/Disable
 *  •   disable() → stops all events from firing.
 *  •   enable() → re-enables them.
 *  2.  Registration
 *  •   register($trigger, $callable, $priority) → adds a single event listener.
 *  •   registerMultiple($events, $priority) → adds several at once.
 *  •   Internally uses registerEvent() and registerClosureEvent() to store listeners.
 *  3.  Triggering
 *  •   trigger($trigger, &...$arguments) → fires all listeners for a given trigger, passing arguments by reference.
 *  •   Listeners can stop propagation by returning false.
 *  4.  Management
 *  •   has($trigger) → checks if listeners exist for a trigger.
 *  •   triggers() → lists all registered triggers.
 *  •   unregister($eventId) → removes a specific listener.
 *  •   unregisterAll($trigger = null) → clears all listeners, or just for one trigger.
 *  5.  Listener Execution
 *  •   Listeners are sorted by priority (highest first).
 *  •   Supports closures directly, or array form [ClassName::class, 'method'] which will be wrapped into a closure that instantiates the class and calls the method.
 *
 * ⸻
 *
 * 4. Error Handling
 *  •   Throws InvalidValue exception if a bad callable is registered.
 *  •   Logs debug information (logMsg) at various points to aid troubleshooting.
 *
 * ⸻
 *
 * 5. Big Picture
 *  •   Event.php provides the hook system for the Orange framework.
 *  •   It allows developers to plug into the lifecycle (like before.router, after.output, etc.) without modifying core code.
 *  •   By centralizing event registration and dispatch, it keeps the system flexible, extensible, and testable.
 *
 * Example Configuration:
 * return [
 *   'before.router' => [
 *       [\app\libraries\OutputCors::class . '::handleCrossOriginResourceSharing', Event::PRIORITY_HIGHEST],
 *       [\app\libraries\Middleware::class . '::beforeRouter'],
 *   ],
 *   'before.controller' => [
 *       [\app\libraries\Middleware::class . '::beforeController'],
 *   ],
 *   'after.controller' => [
 *       [\app\libraries\Middleware::class . '::afterController'],
 *   ],
 *   'after.output' => [
 *       [\app\libraries\Middleware::class . '::afterOutput'],
 *   ],
 *   'some.bogus_Event' => [
 *       ['\app\bogus\class::bogus_method', Event::PRIORITY_LOWEST],
 *   ],
 * ];
 *
 * @package orange\framework
 */
class Event extends Singleton implements EventInterface
{
  /** include ConfigurationTrait methods */
    use ConfigurationTrait;

  /**
   * Stores all registered events grouped by triggers.
   */
    protected array $events = [];

  /**
   * Indicates whether the event manager is disabled.
   */
    protected bool $disabled = false;

  /**
   * Per-process strictly-increasing counter used to keep generated event IDs
   * collision-free; see registerClosureEvent().
   */
    private static int $sequence = 0;

  /**
   * Constructor is protected to enforce Singleton usage.
   *
   * @param array $config Configuration array.
   * @throws InvalidValue If a configured event listener is not a closure, string, or two-element array.
   */
    protected function __construct(array $config)
    {
        logMsg('DEBUG', __METHOD__);

      // Merge provided configuration with default configuration.
        $this->config = $this->mergeConfigWith($config);

        $this->disabled = $this->config['disabled'] ?? $this->disabled;

      // Prevent 'disabled' key from being used as an event.
        unset($this->config['disabled']);

        $this->events = [];

      // Register all configured events
        foreach ($this->config as $trigger => $events) {
            foreach ($events as $options) {
                // option[0] is either a Closure or a string containing the class name and method separated by :: (double colons)
                // option[1] is either empty or a PRIORITY (see interface)
                $this->registerEvent($trigger, $options[0], $options[1] ?? self::PRIORITY_NORMAL);
            }
        }
    }

  /**
   * Disable all event triggers.
   */
    public function disable(): void
    {
        logMsg('DEBUG', __METHOD__);
        $this->disabled = true;
    }

  /**
   * Enable all event triggers.
   */
    public function enable(): void
    {
        logMsg('DEBUG', __METHOD__);
        $this->disabled = false;
    }

  /**
   * Register a single event listener.
   *
   * @param string $trigger Event trigger name.
   * @param \Closure|array $callable Event callback (closure or class-method pair).
   * @param int $priority Priority of the event listener.
   * @return int Event ID for reference.
   * @throws InvalidValue If $callable is not a closure or a two-element array.
   */
    public function register(string $trigger, \Closure|array $callable, int $priority = self::PRIORITY_NORMAL): int
    {
        logMsg('DEBUG', __METHOD__);

        // only build the message/context if this level is enabled - logMsg() alone would build it regardless
        if (isLogEnabled('DEBUG')) {
            logMsg('DEBUG', '', ['trigger' => $trigger, 'callable' => $callable, 'priority' => $priority]);
        }

        return $this->registerEvent($trigger, $callable, $priority);
    }

  /**
   * Register multiple event listeners at once.
   *
   * @param array $multiple Array of event trigger => callable pairs.
   * @param int $priority Priority for all listeners.
   * @return array Array of registered event IDs.
   * @throws InvalidValue If a $multiple callable is not a closure, string, or two-element array.
   */
    public function registerMultiple(array $multiple, int $priority = self::PRIORITY_NORMAL): array
    {
        $registered = [];

        foreach ($multiple as $trigger => $callable) {
            $registered[] = $this->registerEvent($trigger, $callable, $priority);
        }

        return $registered;
    }

  /**
   * Trigger an event.
   *
   * @param string $trigger Event trigger name.
   * @param mixed ...$arguments Arguments passed to event listeners.
   * @return self Fluent interface.
   */
    public function trigger(string $trigger, &...$arguments): self
    {
        logMsg('DEBUG', __METHOD__ . ' ' . $trigger);

        // only build the message/context if this level is enabled - logMsg() alone would build it regardless
        if (isLogEnabled('DEBUG')) {
            logMsg('DEBUG', '', ['trigger' => $trigger, 'arguments' => $arguments, 'disabled' => $this->disabled]);
        }

        if (!$this->disabled) {
            $trigger = $this->normalize($trigger);

            if (isset($this->events[$trigger])) {
                foreach ($this->listeners($trigger) as $listener) {
                    if ($listener(...$arguments) === false) {
                        // Stop processing if listener returns false
                        break;
                    }
                }
            }
        }

        return $this;
    }

  /**
   * Check if any listeners exist for a specific event trigger.
   *
   * @param string $trigger Event trigger name.
   * @return bool True if listeners exist, false otherwise.
   */
    public function has(string $trigger): bool
    {
        // only build the message/context if this level is enabled - logMsg() alone would build it regardless
        if (isLogEnabled('DEBUG')) {
            logMsg('DEBUG', __METHOD__, ['trigger' => $trigger]);
        }

        return isset($this->events[$this->normalize($trigger)]);
    }

  /**
   * Retrieve all registered event triggers.
   *
   * @return array List of all registered event triggers.
   */
    public function triggers(): array
    {
        // only build the message/context if this level is enabled - logMsg() alone would build it regardless
        if (isLogEnabled('DEBUG')) {
            logMsg('DEBUG', __METHOD__, array_keys($this->events));
        }

        return array_keys($this->events);
    }

  /**
   * Unregister a specific event listener by its ID.
   *
   * @param int $eventId Event ID to remove.
   * @return bool True if removed successfully, false otherwise.
   */
    public function unregister(int $eventId): bool
    {
        // only build the message/context if this level is enabled - logMsg() alone would build it regardless
        if (isLogEnabled('DEBUG')) {
            logMsg('DEBUG', __METHOD__, ['eventId' => $eventId]);
        }

        foreach ($this->events as $trigger => &$listeners) {
            if (isset($listeners[$eventId])) {
                unset($listeners[$eventId]);

                if (empty($listeners)) {
                    unset($this->events[$trigger]);
                }
                return true;
            }
        }

        return false;
    }

  /**
   * Unregister all event listeners, optionally for a specific trigger.
   *
   * @param string|null $trigger Event trigger name (optional).
   * @return bool True if listeners were removed.
   */
    public function unregisterAll(?string $trigger = null): bool
    {
        // only build the message/context if this level is enabled - logMsg() alone would build it regardless
        if (isLogEnabled('DEBUG')) {
            logMsg('DEBUG', __METHOD__, ['trigger' => $trigger]);
        }

      // this exits on the first successful removal
        if ($trigger) {
            $trigger = $this->normalize($trigger);

            if (isset($this->events[$trigger])) {
                unset($this->events[$trigger]);
                return true;
            }
        } else {
            $this->events = [];
            return true;
        }

        return false;
    }

  /**
   * Retrieve listeners for a given trigger, sorted by priority.
   *
   * @param string $trigger Event trigger name.
   * @return array Sorted array of listeners.
   */
    protected function listeners(string $trigger): array
    {
        // Listeners are already ordered highest-priority-first at registration
        // time (see registerClosureEvent()), so trigger() - which can fire many
        // times per request - no longer re-sorts on every call.
        return $this->events[$this->normalize($trigger)];
    }

  /**
   * Register an event listener.
   *
   * @param string $trigger Event trigger name.
   * @param \Closure|array|string $callable Callback: a closure, a [ClassName, 'method'] pair, or a 'ClassName::method' string.
   * @param int $priority Priority level.
   * @return int Event ID.
   * @throws InvalidValue If $callable is not a closure, string, or two-element array.
   */
    protected function registerEvent(string $trigger, \Closure|array|string $callable, int $priority): int
    {
        $eventId = 0;

        if ($callable instanceof \Closure) {
          // register a closure
          //
          // function(&$var) {
          //   $var = 'Hello ' . $var. ' how are you?';
          // }
          //
            $eventId = $this->registerClosureEvent($trigger, $callable, $priority);
        } elseif (is_string($callable)) {
          //
          // register a class::method
          //
          // [\app\libraries\Middleware::class.'::before']
            $eventId = $this->registerClosureEvent($trigger, function (&...$arguments) use ($callable) {
                [$className, $methodName] = explode('::', $callable, 2);

                return new $className()->$methodName(...$arguments);
            }, $priority);
        } elseif (count($callable) == 2) {
          //
          // register a class & method
          //
          // [\app\libraries\Middleware::class,'before']
            $eventId = $this->registerClosureEvent($trigger, function (&...$arguments) use ($callable) {
                [$className, $methodName] = $callable;

                return new $className()->$methodName(...$arguments);
            }, $priority);
        } else {
            throw new InvalidValue(__METHOD__ . ' trigger "' . $trigger . '"');
        }

        return $eventId;
    }

  /**
   * Register a closure event listener.
   * This method generates a unique event ID based on the priority and a per-process
   * sequence number, and stores the closure in the events array under the
   * normalized trigger name.
   *
   * @param string $trigger
   * @param Closure $callable
   * @param int $priority
   * @return int
   */
    protected function registerClosureEvent(string $trigger, \Closure $callable, int $priority): int
    {
      // Priority occupies the high-order digits so krsort() in listeners() sorts
      // purely by array key and still ends up highest-priority-first; the low
      // twelve digits are a strictly increasing per-process sequence number so two
      // registrations - even at identical priority - never collide.
      //
      // Previously this concatenated $priority with hrtime(true) as strings and cast
      // the result with intval(). hrtime(true) grows without bound as the process/
      // system keeps running, and once the combined digit count passed PHP_INT_MAX
      // (~19 digits), intval() silently clamped every subsequent ID to the same
      // value - collapsing distinct listeners onto the same array key and silently
      // dropping earlier ones. The sequence counter here is bounded by how many
      // events get registered, not by wall-clock/uptime, so it can't drift into an
      // overflow the way a raw timestamp can.
        $eventId = ($priority * 1_000_000_000_000) + (self::$sequence++ % 1_000_000_000_000);

        $normalizedTrigger = $this->normalize($trigger);

        $this->events[$normalizedTrigger][$eventId] = $callable;

        // Sort this trigger's listeners highest-priority-first now, at
        // registration time (infrequent), rather than on every trigger() fire.
        // unregister()/unregisterAll() only remove keys, so the order set here
        // is preserved until the next registration.
        krsort($this->events[$normalizedTrigger]);

        return $eventId;
    }
}
