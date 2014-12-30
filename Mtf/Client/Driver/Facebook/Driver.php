<?php
/**
 * {license_notice}
 *
 * @copyright   {copyright}
 * @license     {license_link}
 */
namespace Mtf\Client\Driver\Facebook;

use Mtf\System\Config;
use Mtf\ObjectManager;
use Mtf\Client\Locator;
use Mtf\Client\DriverInterface;
use Mtf\Client\ElementInterface;
use Mtf\System\Event\EventManagerInterface;

/**
 * Class Driver
 */
final class Driver implements DriverInterface
{
    /**
     * Remote driver
     *
     * @var RemoteDriver
     */
    protected $driver;

    /**
     * Configuration for driver
     *
     * @var Config
     */
    protected $configuration;

    /**
     * Event manager to manage events
     *
     * @var EventManagerInterface
     */
    protected $eventManager;

    /**
     * Application object manager
     *
     * @var ObjectManager
     */
    protected $objectManager;

    /**
     * Constructor
     *
     * @param RemoteDriver $driver
     * @param EventManagerInterface $eventManager
     * @param Config $configuration
     * @param ObjectManager $objectManager
     */
    public function __construct(
        RemoteDriver $driver,
        EventManagerInterface $eventManager,
        Config $configuration,
        ObjectManager $objectManager
    ) {
        $this->driver = $driver;
        $this->configuration = $configuration;
        $this->eventManager = $eventManager;
        $this->objectManager = $objectManager;

        $this->init();
    }

    /**
     * Destructor
     *
     * @return void
     */
    public function __destruct()
    {
        $this->closeWindow();
    }

    /**
     * Initialize driver
     *
     * @return void
     */
    protected function init()
    {
        $this->driver->get('about:blank');
        $this->driver->manage()->deleteAllCookies();
        $this->driver->manage()->window()->maximize();
        $this->driver->navigate()->refresh();
    }

    /**
     * Get native element
     *
     * @param Locator $locator
     * @param \RemoteWebElement $context
     * @param bool $wait
     * @return \RemoteWebElement
     */
    protected function getElement(Locator $locator, \RemoteWebElement $context = null, $wait = true)
    {
        $context = $context === null
            ? $this->driver
            : $context;

        $criteria = $this->getSearchCriteria($locator);

        if ($wait) {
            return $this->waitUntil(
                function () use ($context, $criteria) {
                    return $context->findElement($criteria)->isDisplayed()
                        ? $context->findElement($criteria)
                        : null ;
                }
            );
        }

        $driver = $this->driver;
        $this->waitUntil(
            function () use ($driver) {
                $result = $driver->executeScript("return document['readyState']", []);
                return $result === 'complete' || $result === 'uninitialized';
            }
        );

        return $context->findElement($criteria);
    }

    /**
     * Get context element
     *
     * @param ElementInterface $element
     * @return null|\RemoteWebElement
     */
    protected function getContext(ElementInterface $element = null)
    {
        $elements = [];
        $contextElement = null;
        if ($element === null) {
            return $contextElement;
        }

        $elements[] = $element;
        while($element = $element->getContext()) {
            $elements[] = $element;
        }

        /** @var ElementInterface $element */
        foreach (array_reverse($elements) as $element) {
            // First call "getElement" with $contextElement equal "null" value
            $contextElement = $this->getElement($element->getLocator(), $contextElement);
        }

        return $contextElement;
    }

    /**
     * Get search criteria
     *
     * @param Locator $locator
     * @return \WebDriverBy
     */
    public function getSearchCriteria(Locator $locator)
    {
        $value = $locator['value'];
        switch ($locator['using']) {
            case Locator::SELECTOR_XPATH:
                return \WebDriverBy::xpath($value);
            case Locator::SELECTOR_ID:
                return \WebDriverBy::id($value);
            case Locator::SELECTOR_NAME:
                return \WebDriverBy::name($value);
            case Locator::SELECTOR_CLASS_NAME:
                return \WebDriverBy::className($value);
            case Locator::SELECTOR_TAG_NAME:
                return \WebDriverBy::tagName($value);
            case Locator::SELECTOR_LINK_TEXT:
                return \WebDriverBy::linkText($value);
            case Locator::SELECTOR_CSS:
            default:
                return \WebDriverBy::cssSelector($value);
        }
    }

    /**
     * Click
     *
     * @param ElementInterface $element
     * @return void
     */
    public function click(ElementInterface $element)
    {
        $this->eventManager->dispatchEvent(['click_before'], [__METHOD__, $element->getAbsoluteSelector()]);
        $this->getElement($element->getLocator(), $this->getContext($element->getContext()))->click();
        $this->eventManager->dispatchEvent(['click_after'], [__METHOD__, $element->getAbsoluteSelector()]);
    }

    /**
     * Double click
     *
     * @param ElementInterface $element
     * @return void
     */
    public function doubleClick(ElementInterface $element)
    {
        $this->driver->action()
            ->doubleClick($this->getElement($element->getLocator(), $this->getContext($element->getContext())))
            ->perform();
    }

    /**
     * Right click
     *
     * @param ElementInterface $element
     * @return void
     * @throws \Exception
     */
    public function rightClick(ElementInterface $element)
    {
        throw new \Exception('To use this action, extend the native driver!');
    }

    /**
     * Check whether element is visible
     *
     * @param ElementInterface $element
     * @return bool
     */
    public function isVisible(ElementInterface $element)
    {
        try {
            $visible = $this->getElement(
                $element->getLocator(),
                $this->getContext($element->getContext()),
                false
            )->isDisplayed();
        } catch (\Exception $e) {
            $visible = false;
        }

        return $visible;
    }

    /**
     * Check whether element is enabled
     *
     * @param ElementInterface $element
     * @return bool
     */
    public function isDisabled(ElementInterface $element)
    {
        return !$this->getElement($element->getLocator(), $this->getContext($element->getContext()))->isEnabled();
    }

    /**
     * Check whether element is selected
     *
     * @param ElementInterface $element
     * @return bool
     */
    public function isSelected(ElementInterface $element)
    {
        return $this->getElement($element->getLocator(), $this->getContext($element->getContext()))->isSelected();
    }

    /**
     * Set the value
     *
     * @param ElementInterface $element
     * @param string|array $value
     * @return void
     */
    public function setValue(ElementInterface $element, $value)
    {
        $wrappedElement = $this->getElement($element->getLocator(), $this->getContext($element->getContext()));
        $wrappedElement->clear();
        $wrappedElement->sendKeys($value);
    }

    /**
     * Get the value
     *
     * @param ElementInterface $element
     * @return null|string
     */
    public function getValue(ElementInterface $element)
    {
        return $this->getElement($element->getLocator(), $this->getContext($element->getContext()))
            ->getAttribute('value');
    }

    /**
     * Get content
     *
     * @param ElementInterface $element
     * @return string
     */
    public function getText(ElementInterface $element)
    {
        return $this->getElement($element->getLocator(), $this->getContext($element->getContext()))->getText();
    }

    /**
     * Find element on the page
     *
     * @param string $selector
     * @param string $strategy
     * @param string $type = select|multiselect|checkbox|null OR custom class with full namespace
     * @param ElementInterface $context
     * @return ElementInterface
     * @throws \Exception
     */
    public function find(
        $selector,
        $strategy = Locator::SELECTOR_CSS,
        $type = null,
        ElementInterface $context = null
    ) {
        $this->eventManager->dispatchEvent(['find'], [__METHOD__, sprintf('(%s -> %s)', $strategy, $selector)]);

        $locator = $this->objectManager->create(
            'Mtf\Client\Locator',
            [
                'value' => $selector,
                'strategy' => $strategy
            ]
        );

        $className = 'Mtf\Client\ElementInterface';
        if (null !== $type) {
            if (strpos($type, '\\') === false) {
                $type = ucfirst(strtolower($type));
                if (class_exists('Mtf\Client\Element\\' . $type . 'Element')) {
                    $className = 'Mtf\Client\Element\\' . $type . 'Element';
                }
            } else {
                if (!class_exists($type) && !interface_exists($type)) {
                    throw new \Exception('Requested interface or class does not exists!');
                }
                $className = $type;
            }
        }

        return $this->objectManager->create(
            $className,
            [
                'driver' => $this,
                'locator' => $locator,
                'context' => $context
            ]
        );
    }

    /**
     * Drag and drop element to(between) another element(s)
     *
     * @param ElementInterface $element
     * @param ElementInterface $target
     * @return void
     */
    public function dragAndDrop(ElementInterface $element, ElementInterface $target)
    {
        $this->driver->action()
            ->dragAndDrop(
                $this->getElement($element->getLocator(), $this->getContext($element->getContext())),
                $this->getElement($target->getLocator(), $this->getContext($target->getContext()))
            )->perform();
    }

    /**
     * Send a sequence of key strokes to the active element.
     *
     * @param ElementInterface $element
     * @param array $keys
     * @return void
     */
    public function keys(ElementInterface $element, array $keys)
    {
        $this->getElement($element->getLocator(), $this->getContext($element->getContext()))->sendKeys($keys);
    }

    /**
     * Wait until callback isn't null or timeout occurs
     *
     * @param callable $callback
     * @return mixed
     * @throws \Exception
     */
    public function waitUntil($callback)
    {
        try {
            return $this->driver->wait()->until($callback);
        } catch (\Exception $e) {
            throw new \Exception(
                sprintf("Error occurred during waiting for an element with message (%s)",$e->getMessage())
            );
        }
    }

    /**
     * Get all elements by locator
     *
     * @param ElementInterface $context
     * @param string $selector
     * @param string $strategy
     * @param null|string $type
     * @return ElementInterface[]
     * @throws \Exception
     */
    public function getElements(
        ElementInterface $context,
        $selector,
        $strategy = Locator::SELECTOR_CSS,
        $type = null
    ) {
        $locator = $this->objectManager->create(
            'Mtf\Client\Locator',
            [
                'value' => $selector,
                'strategy' => $strategy
            ]
        );
        $resultElements = [];
        $elements = $this->getElement($context->getLocator(), $this->getContext($context->getContext()))
            ->findElements($this->getSearchCriteria($locator));
        for ($length = count($elements), $i = 0; $i < $length; ++$i) {
            $resultElements[$i] = $this->find($selector, $strategy, $type, $context);
        }

        return $resultElements;
    }

    /**
     * Get the value of a the given attribute of the element
     *
     * @param ElementInterface $element
     * @param string $name
     * @return string
     */
    public function getAttribute(ElementInterface $element, $name)
    {
        return $this->getElement($element->getLocator(), $this->getContext($element->getContext()))
            ->getAttribute($name);
    }

    /**
     * Open page
     *
     * @param string $url
     * @return void
     */
    public function open($url)
    {
        $this->eventManager->dispatchEvent(['open_before'], [__METHOD__, $url]);
        $this->driver->get($url);
        $this->eventManager->dispatchEvent(['open_after'], [__METHOD__, $url]);
    }

    /**
     * Back to previous page
     *
     * @return void
     */
    public function back()
    {
        $this->eventManager->dispatchEvent(['back'], [__METHOD__]);
        $this->driver->navigate()->back();
    }

    /**
     * Forward page
     *
     * @return void
     */
    public function forward()
    {
        $this->eventManager->dispatchEvent(['forward'], [__METHOD__]);
        $this->driver->navigate()->forward();
    }

    /**
     * Refresh page
     *
     * @return void
     */
    public function refresh()
    {
        $this->eventManager->dispatchEvent(['refresh'], [__METHOD__]);
        $this->driver->navigate()->refresh();
    }

    /**
     * Reopen browser
     *
     * @return void
     */
    public function reopen()
    {
        $this->eventManager->dispatchEvent(['reopen'], [__METHOD__]);
        $this->closeWindow();
        $this->driver->createNewSession();
        $this->init();
    }

    /**
     * Change the focus to a frame in the page by locator
     *
     * @param Locator $locator
     * @return void
     */
    public function switchToFrame(Locator $locator)
    {
        $this->eventManager->dispatchEvent(['switch_to_frame'], [(string) $locator]);
        $this->driver->switchTo()->frame($this->getElement($locator));
    }

    /**
     * Close the current window
     *
     * @return void
     */
    public function closeWindow()
    {
        $this->driver->quit();
    }

    /**
     * Select window by its name
     *
     * @return void
     */
    public function selectWindow()
    {
        $windowHandles = $this->driver->getWindowHandles();
        $this->driver->switchTo()->window(end($windowHandles));
    }

    /**
     * Press OK on an alert, or confirms a dialog
     *
     * @return void
     */
    public function acceptAlert()
    {
        $this->driver->switchTo()->alert()->accept();
        $this->eventManager->dispatchEvent(['accept_alert_after'], [__METHOD__]);
    }

    /**
     * Press Cancel on an alert, or does not confirm a dialog
     *
     * @return void
     */
    public function dismissAlert()
    {
        $this->driver->switchTo()->alert()->dismiss();
        $this->eventManager->dispatchEvent(['dismiss_alert_after'], [__METHOD__]);
    }

    /**
     * Get the alert dialog text
     *
     * @return string
     */
    public function getAlertText()
    {
        $this->eventManager->dispatchEvent(['get_alert_text'], [__METHOD__]);
        return $this->driver->switchTo()->alert()->getText();
    }

    /**
     * Set the text to a prompt popup
     *
     * @param string $text
     * @return void
     */
    public function setAlertText($text)
    {
        $this->driver->switchTo()->alert()->sendKeys($text);
    }

    /**
     * Get current page url
     *
     * @return string
     */
    public function getUrl()
    {
        try {
            if ($this->driver->switchTo()->alert()->getText()) {
                return null;
            }
        } catch (\Exception $exception) {
            return $this->driver->getCurrentURL();
        }

        return $this->driver->getCurrentURL();
    }

    /**
     * Get Html page source
     *
     * @return string
     */
    public function getHtmlSource()
    {
        return $this->driver->getPageSource();
    }

    /**
     * Get binary string of image
     *
     * @return string
     */
    public function getScreenshotData()
    {
        return $this->driver->takeScreenshot();
    }

    /**
     * Inject Js Error collector
     *
     * @return mixed
     */
    public function injectJsErrorCollector()
    {
        $script = '
            window.onerror = function(msg, url, line) {
                var errors = {};
                if (localStorage.getItem("errorsHistory")) {
                    errors = JSON.parse(localStorage.getItem("errorsHistory"));
                }
                if (!(window.location.href in errors)) {
                    errors[window.location.href] = [];
                }
                errors[window.location.href].push("error: \'" + msg + "\' " + "file: " + url + " " + "line: " + line);
                localStorage.setItem("errorsHistory", JSON.stringify(errors));
            }
        ';

        return $this->driver->executeScript($script, []);
    }

    /**
     * Get js errors
     *
     * @return mixed
     */
    public function getJsErrors()
    {
        $script = '
            errors = JSON.parse(localStorage.getItem("errorsHistory"));
            localStorage.removeItem("errorsHistory");
            return errors;
        ';

        return $this->driver->executeScript($script, []);
    }
}
