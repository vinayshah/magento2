<?php
/**
 * Copyright © Magento, Inc. All rights reserved.
 * See COPYING.txt for license details.
 */
declare(strict_types=1);

namespace Magento\Braintree\Test\Unit\Controller\Paypal;

use Magento\Braintree\Block\Paypal\Checkout\Review as CheckoutReview;
use Magento\Braintree\Controller\Paypal\Review;
use Magento\Braintree\Gateway\Config\PayPal\Config;
use Magento\Braintree\Model\Paypal\Helper\QuoteUpdater;
use Magento\Checkout\Model\Session;
use Magento\Framework\App\Action\Context;
use Magento\Framework\App\RequestInterface;
use Magento\Framework\Controller\Result\Redirect;
use Magento\Framework\Controller\ResultFactory;
use Magento\Framework\Exception\LocalizedException;
use Magento\Framework\Message\ManagerInterface;
use Magento\Framework\View\Element\AbstractBlock;
use Magento\Framework\View\Layout;
use Magento\Framework\View\Result\Page;
use Magento\Payment\Model\Method\Logger;
use Magento\Quote\Model\Quote;
use Magento\Quote\Model\Quote\Payment;
use PHPUnit\Framework\MockObject\MockObject;
use PHPUnit\Framework\TestCase;

/**
 * @see \Magento\Braintree\Controller\Paypal\Review
 *
 * @SuppressWarnings(PHPMD.CouplingBetweenObjects)
 */
class ReviewTest extends TestCase
{
    /**
     * @var QuoteUpdater|MockObject
     */
    private $quoteUpdaterMock;

    /**
     * @var Config|MockObject
     */
    private $configMock;

    /**
     * @var Session|MockObject
     */
    private $checkoutSessionMock;

    /**
     * @var RequestInterface|MockObject
     */
    private $requestMock;

    /**
     * @var ResultFactory|MockObject
     */
    private $resultFactoryMock;

    /**
     * @var ManagerInterface|MockObject
     */
    private $messageManagerMock;

    /**
     * @var Review
     */
    private $review;

    /**
     * @var Logger|MockObject
     */
    private $loggerMock;

    protected function setUp(): void
    {
        /** @var Context|MockObject $contextMock */
        $contextMock = $this->getMockBuilder(Context::class)
            ->disableOriginalConstructor()
            ->getMock();
        $this->requestMock = $this->getMockBuilder(RequestInterface::class)
            ->setMethods(['getPostValue'])
            ->getMockForAbstractClass();
        $this->resultFactoryMock = $this->getMockBuilder(ResultFactory::class)
            ->disableOriginalConstructor()
            ->getMock();
        $this->checkoutSessionMock = $this->getMockBuilder(Session::class)
            ->disableOriginalConstructor()
            ->getMock();
        $this->configMock = $this->getMockBuilder(Config::class)
            ->disableOriginalConstructor()
            ->getMock();
        $this->quoteUpdaterMock = $this->getMockBuilder(QuoteUpdater::class)
            ->disableOriginalConstructor()
            ->getMock();
        $this->messageManagerMock = $this->getMockBuilder(ManagerInterface::class)
            ->getMockForAbstractClass();
        $this->loggerMock = $this->getMockBuilder(Logger::class)
            ->disableOriginalConstructor()
            ->getMock();

        $contextMock->expects(self::once())
            ->method('getRequest')
            ->willReturn($this->requestMock);
        $contextMock->expects(self::once())
            ->method('getResultFactory')
            ->willReturn($this->resultFactoryMock);
        $contextMock->expects(self::once())
            ->method('getMessageManager')
            ->willReturn($this->messageManagerMock);

        $this->review = new Review(
            $contextMock,
            $this->configMock,
            $this->checkoutSessionMock,
            $this->quoteUpdaterMock,
            $this->loggerMock
        );
    }

    public function testExecute()
    {
        $result = '{"nonce": ["test-value"], "details": ["test-value"]}';

        $resultPageMock = $this->getResultPageMock();
        $layoutMock = $this->getLayoutMock();
        $blockMock = $this->getBlockMock();
        $quoteMock = $this->getQuoteMock();
        $childBlockMock = $this->getChildBlockMock();

        $quoteMock->expects(self::once())
            ->method('getItemsCount')
            ->willReturn(1);

        $this->requestMock->expects(self::once())
            ->method('getPostValue')
            ->with('result', '{}')
            ->willReturn($result);

        $this->checkoutSessionMock->expects(self::once())
            ->method('getQuote')
            ->willReturn($quoteMock);

        $this->quoteUpdaterMock->expects(self::once())
            ->method('execute')
            ->with(['test-value'], ['test-value'], $quoteMock);

        $this->resultFactoryMock->expects(self::once())
            ->method('create')
            ->with(ResultFactory::TYPE_PAGE)
            ->willReturn($resultPageMock);

        $resultPageMock->expects(self::once())
            ->method('getLayout')
            ->willReturn($layoutMock);

        $layoutMock->expects(self::once())
            ->method('getBlock')
            ->with('braintree.paypal.review')
            ->willReturn($blockMock);

        $blockMock->expects(self::once())
            ->method('setQuote')
            ->with($quoteMock);
        $blockMock->expects(self::once())
            ->method('getChildBlock')
            ->with('shipping_method')
            ->willReturn($childBlockMock);

        $childBlockMock->expects(self::once())
            ->method('setData')
            ->with('quote', $quoteMock);

        self::assertEquals($this->review->execute(), $resultPageMock);
    }

    public function testExecuteException()
    {
        $result = '{}';
        $quoteMock = $this->getQuoteMock();
        $resultRedirectMock = $this->getResultRedirectMock();

        $quoteMock->expects(self::once())
            ->method('getItemsCount')
            ->willReturn(0);

        $this->requestMock->expects(self::once())
            ->method('getPostValue')
            ->with('result', '{}')
            ->willReturn($result);

        $this->checkoutSessionMock->expects(self::once())
            ->method('getQuote')
            ->willReturn($quoteMock);

        $this->quoteUpdaterMock->expects(self::never())
            ->method('execute');

        $this->messageManagerMock->expects(self::once())
            ->method('addExceptionMessage')
            ->with(
                self::isInstanceOf('\InvalidArgumentException'),
                'Checkout failed to initialize. Verify and try again.'
            );

        $this->resultFactoryMock->expects(self::once())
            ->method('create')
            ->with(ResultFactory::TYPE_REDIRECT)
            ->willReturn($resultRedirectMock);

        $resultRedirectMock->expects(self::once())
            ->method('setPath')
            ->with('checkout/cart')
            ->willReturnSelf();

        self::assertEquals($this->review->execute(), $resultRedirectMock);
    }

    public function testExecuteExceptionPaymentWithoutNonce()
    {
        $result = '{}';
        $quoteMock = $this->getQuoteMock();
        $resultRedirectMock = $this->getResultRedirectMock();

        $quoteMock->expects(self::once())
            ->method('getItemsCount')
            ->willReturn(1);

        $paymentMock = $this->getMockBuilder(Payment::class)
            ->disableOriginalConstructor()
            ->getMock();

        $quoteMock->expects(self::once())
            ->method('getPayment')
            ->willReturn($paymentMock);

        $this->requestMock->expects(self::once())
            ->method('getPostValue')
            ->with('result', '{}')
            ->willReturn($result);

        $this->checkoutSessionMock->expects(self::once())
            ->method('getQuote')
            ->willReturn($quoteMock);

        $this->messageManagerMock->expects(self::once())
            ->method('addExceptionMessage')
            ->with(
                self::isInstanceOf(LocalizedException::class),
                'Checkout failed to initialize. Verify and try again.'
            );

        $this->resultFactoryMock->expects(self::once())
            ->method('create')
            ->with(ResultFactory::TYPE_REDIRECT)
            ->willReturn($resultRedirectMock);

        $resultRedirectMock->expects(self::once())
            ->method('setPath')
            ->with('checkout/cart')
            ->willReturnSelf();

        self::assertEquals($this->review->execute(), $resultRedirectMock);
    }

    /**
     * @return Redirect|MockObject
     */
    private function getResultRedirectMock()
    {
        return $this->getMockBuilder(Redirect::class)
            ->disableOriginalConstructor()
            ->getMock();
    }

    /**
     * @return AbstractBlock|MockObject
     */
    private function getChildBlockMock()
    {
        return $this->getMockBuilder(AbstractBlock::class)
            ->disableOriginalConstructor()
            ->getMock();
    }

    /**
     * @return CheckoutReview|MockObject
     */
    private function getBlockMock()
    {
        return $this->getMockBuilder(CheckoutReview::class)
            ->disableOriginalConstructor()
            ->getMock();
    }

    /**
     * @return Layout|MockObject
     */
    private function getLayoutMock()
    {
        return $this->getMockBuilder(Layout::class)
            ->disableOriginalConstructor()
            ->getMock();
    }

    /**
     * @return Page|MockObject
     */
    private function getResultPageMock()
    {
        return $this->getMockBuilder(Page::class)
            ->disableOriginalConstructor()
            ->getMock();
    }

    /**
     * @return Quote|MockObject
     */
    private function getQuoteMock()
    {
        return $this->getMockBuilder(Quote::class)
            ->disableOriginalConstructor()
            ->getMock();
    }
}
