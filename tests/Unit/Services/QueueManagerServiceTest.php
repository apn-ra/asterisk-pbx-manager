<?php

namespace AsteriskPbxManager\Tests\Unit\Services;

use AsteriskPbxManager\Exceptions\ActionExecutionException;
use AsteriskPbxManager\Services\AsteriskManagerService;
use AsteriskPbxManager\Services\QueueManagerService;
use AsteriskPbxManager\Tests\Unit\UnitTestCase;
use Illuminate\Support\Facades\Log;
use Mockery;
use PAMI\Message\Action\QueueAddAction;
use PAMI\Message\Action\QueuePauseAction;
use PAMI\Message\Action\QueueRemoveAction;
use PAMI\Message\Action\QueueStatusAction;
use PAMI\Message\Action\QueueSummaryAction;

class QueueManagerServiceTest extends UnitTestCase
{
    private QueueManagerService $service;
    private $mockAsteriskManager;

    protected function setUp(): void
    {
        parent::setUp();

        $this->mockAsteriskManager = Mockery::mock(AsteriskManagerService::class);
        $this->service = new QueueManagerService($this->mockAsteriskManager);
    }

    public function testConstructorSetsAsteriskManagerCorrectly()
    {
        $asteriskManager = Mockery::mock(AsteriskManagerService::class);
        $service = new QueueManagerService($asteriskManager);

        $this->assertInstanceOf(QueueManagerService::class, $service);
    }

    public function testAddMemberSuccess()
    {
        $mockResponse = $this->createMockResponse(true, 'Success', []);

        $this->mockAsteriskManager
            ->shouldReceive('send')
            ->once()
            ->with(Mockery::type(QueueAddAction::class))
            ->andReturn($mockResponse);

        Log::shouldReceive('info')
            ->once()
            ->with('Queue member added successfully', Mockery::any());

        $result = $this->service->addMember('support', 'SIP/1001', 'John Doe', 0);

        $this->assertTrue($result);
    }

    public function testAddMemberFailure()
    {
        $mockResponse = $this->createMockResponse(false, 'Queue not found', []);

        $this->mockAsteriskManager
            ->shouldReceive('send')
            ->once()
            ->with(Mockery::type(QueueAddAction::class))
            ->andReturn($mockResponse);

        Log::shouldReceive('error')
            ->once()
            ->with('Failed to add queue member', Mockery::any());

        $result = $this->service->addMember('support', 'SIP/1001', 'John Doe', 0);

        $this->assertFalse($result);
    }

    public function testAddMemberWithInvalidQueueName()
    {
        $this->expectException(\InvalidArgumentException::class);
        $this->expectExceptionMessage('Queue name cannot be empty');

        $this->service->addMember('', 'SIP/1001', 'John Doe', 0);
    }

    public function testAddMemberWithInvalidInterface()
    {
        $this->expectException(\InvalidArgumentException::class);
        $this->expectExceptionMessage('Interface cannot be empty');

        $this->service->addMember('support', '', 'John Doe', 0);
    }

    public function testRemoveMemberSuccess()
    {
        $mockResponse = $this->createMockResponse(true, 'Success', []);

        $this->mockAsteriskManager
            ->shouldReceive('send')
            ->once()
            ->with(Mockery::type(QueueRemoveAction::class))
            ->andReturn($mockResponse);

        Log::shouldReceive('info')
            ->once()
            ->with('Queue member removed successfully', Mockery::any());

        $result = $this->service->removeMember('support', 'SIP/1001');

        $this->assertTrue($result);
    }

    public function testRemoveMemberFailure()
    {
        $mockResponse = $this->createMockResponse(false, 'Member not found', []);

        $this->mockAsteriskManager
            ->shouldReceive('send')
            ->once()
            ->with(Mockery::type(QueueRemoveAction::class))
            ->andReturn($mockResponse);

        Log::shouldReceive('error')
            ->once()
            ->with('Failed to remove queue member', Mockery::any());

        $result = $this->service->removeMember('support', 'SIP/1001');

        $this->assertFalse($result);
    }

    public function testRemoveMemberWithInvalidQueueName()
    {
        $this->expectException(\InvalidArgumentException::class);
        $this->expectExceptionMessage('Queue name cannot be empty');

        $this->service->removeMember('', 'SIP/1001');
    }

    public function testRemoveMemberWithInvalidInterface()
    {
        $this->expectException(\InvalidArgumentException::class);
        $this->expectExceptionMessage('Interface cannot be empty');

        $this->service->removeMember('support', '');
    }

    public function testPauseMemberSuccess()
    {
        $mockResponse = $this->createMockResponse(true, 'Success', []);

        $this->mockAsteriskManager
            ->shouldReceive('send')
            ->once()
            ->with(Mockery::type(QueuePauseAction::class))
            ->andReturn($mockResponse);

        Log::shouldReceive('info')
            ->once()
            ->with('Queue member paused successfully', Mockery::any());

        $result = $this->service->pauseMember('support', 'SIP/1001', true, 'Break time');

        $this->assertTrue($result);
    }

    public function testPauseMemberFailure()
    {
        $mockResponse = $this->createMockResponse(false, 'Member not found', []);

        $this->mockAsteriskManager
            ->shouldReceive('send')
            ->once()
            ->with(Mockery::type(QueuePauseAction::class))
            ->andReturn($mockResponse);

        Log::shouldReceive('error')
            ->once()
            ->with('Failed to pause queue member', Mockery::any());

        $result = $this->service->pauseMember('support', 'SIP/1001', true, 'Break time');

        $this->assertFalse($result);
    }

    public function testUnpauseMemberSuccess()
    {
        $mockResponse = $this->createMockResponse(true, 'Success', []);

        $this->mockAsteriskManager
            ->shouldReceive('send')
            ->once()
            ->with(Mockery::type(QueuePauseAction::class))
            ->andReturn($mockResponse);

        Log::shouldReceive('info')
            ->once()
            ->with('Queue member unpaused successfully', Mockery::any());

        $result = $this->service->pauseMember('support', 'SIP/1001', false, null);

        $this->assertTrue($result);
    }

    public function testPauseMemberWithInvalidQueueName()
    {
        $this->expectException(\InvalidArgumentException::class);
        $this->expectExceptionMessage('Queue name cannot be empty');

        $this->service->pauseMember('', 'SIP/1001', true, 'Break time');
    }

    public function testPauseMemberWithInvalidInterface()
    {
        $this->expectException(\InvalidArgumentException::class);
        $this->expectExceptionMessage('Interface cannot be empty');

        $this->service->pauseMember('support', '', true, 'Break time');
    }

    public function testGetQueueStatusSuccess()
    {
        $mockResponse = $this->createMockResponse(true, 'Success', [
            'Queue'     => 'support',
            'Max'       => '10',
            'Strategy'  => 'ringall',
            'Calls'     => '2',
            'Holdtime'  => '45',
            'TalkTime'  => '120',
            'Completed' => '50',
            'Abandoned' => '5',
        ]);

        $this->mockAsteriskManager
            ->shouldReceive('send')
            ->once()
            ->with(Mockery::type(QueueStatusAction::class))
            ->andReturn($mockResponse);

        Log::shouldReceive('info')
            ->once()
            ->with('Queue status retrieved successfully', Mockery::any());

        $result = $this->service->getQueueStatus('support');

        $this->assertIsArray($result);
        $this->assertArrayHasKey('queue', $result);
        $this->assertArrayHasKey('max_members', $result);
        $this->assertArrayHasKey('strategy', $result);
        $this->assertArrayHasKey('calls_waiting', $result);
        $this->assertEquals('support', $result['queue']);
        $this->assertEquals('10', $result['max_members']);
        $this->assertEquals('ringall', $result['strategy']);
        $this->assertEquals('2', $result['calls_waiting']);
    }

    public function testGetQueueStatusForAllQueues()
    {
        $mockResponse = $this->createMockResponse(true, 'Success', []);

        $this->mockAsteriskManager
            ->shouldReceive('send')
            ->once()
            ->with(Mockery::type(QueueStatusAction::class))
            ->andReturn($mockResponse);

        Log::shouldReceive('info')
            ->once()
            ->with('Queue status retrieved successfully', Mockery::any());

        $result = $this->service->getQueueStatus(null);

        $this->assertIsArray($result);
    }

    public function testGetQueueStatusFailure()
    {
        $mockResponse = $this->createMockResponse(false, 'Permission denied', []);

        $this->mockAsteriskManager
            ->shouldReceive('send')
            ->once()
            ->with(Mockery::type(QueueStatusAction::class))
            ->andReturn($mockResponse);

        Log::shouldReceive('error')
            ->once()
            ->with('Failed to get queue status', Mockery::any());

        $result = $this->service->getQueueStatus('support');

        $this->assertIsArray($result);
        $this->assertEmpty($result);
    }

    public function testGetQueueSummarySuccess()
    {
        $mockResponse = $this->createMockResponse(true, 'Success', [
            'Queue'           => 'support',
            'LoggedIn'        => '5',
            'Available'       => '3',
            'Callers'         => '2',
            'HoldTime'        => '30',
            'TalkTime'        => '90',
            'LongestHoldTime' => '120',
        ]);

        $this->mockAsteriskManager
            ->shouldReceive('send')
            ->once()
            ->with(Mockery::type(QueueSummaryAction::class))
            ->andReturn($mockResponse);

        Log::shouldReceive('info')
            ->once()
            ->with('Queue summary retrieved successfully', Mockery::any());

        $result = $this->service->getQueueSummary('support');

        $this->assertIsArray($result);
        $this->assertArrayHasKey('queue', $result);
        $this->assertArrayHasKey('logged_in', $result);
        $this->assertArrayHasKey('available', $result);
        $this->assertArrayHasKey('callers', $result);
        $this->assertEquals('support', $result['queue']);
        $this->assertEquals('5', $result['logged_in']);
        $this->assertEquals('3', $result['available']);
        $this->assertEquals('2', $result['callers']);
    }

    public function testGetQueueSummaryForAllQueues()
    {
        $mockResponse = $this->createMockResponse(true, 'Success', []);

        $this->mockAsteriskManager
            ->shouldReceive('send')
            ->once()
            ->with(Mockery::type(QueueSummaryAction::class))
            ->andReturn($mockResponse);

        Log::shouldReceive('info')
            ->once()
            ->with('Queue summary retrieved successfully', Mockery::any());

        $result = $this->service->getQueueSummary(null);

        $this->assertIsArray($result);
    }

    public function testGetQueueSummaryFailure()
    {
        $mockResponse = $this->createMockResponse(false, 'Queue not found', []);

        $this->mockAsteriskManager
            ->shouldReceive('send')
            ->once()
            ->with(Mockery::type(QueueSummaryAction::class))
            ->andReturn($mockResponse);

        Log::shouldReceive('error')
            ->once()
            ->with('Failed to get queue summary', Mockery::any());

        $result = $this->service->getQueueSummary('support');

        $this->assertIsArray($result);
        $this->assertEmpty($result);
    }

    public function testGetQueueMembersSuccess()
    {
        $mockResponse = $this->createMockResponse(true, 'Success', []);

        $this->mockAsteriskManager
            ->shouldReceive('send')
            ->once()
            ->with(Mockery::type(QueueStatusAction::class))
            ->andReturn($mockResponse);

        Log::shouldReceive('info')
            ->once()
            ->with('Queue status retrieved successfully', Mockery::any());

        $result = $this->service->getQueueMembers('support');

        $this->assertIsArray($result);
    }

    public function testGetQueueMembersWithInvalidQueueName()
    {
        $this->expectException(\InvalidArgumentException::class);
        $this->expectExceptionMessage('Queue name cannot be empty');

        $this->service->getQueueMembers('');
    }

    public function testMemberExistsTrue()
    {
        $mockResponse = $this->createMockResponse(true, 'Success', [
            'members' => [
                ['interface' => 'SIP/1001', 'name' => 'John Doe'],
                ['interface' => 'SIP/1002', 'name' => 'Jane Smith'],
            ],
        ]);

        $this->mockAsteriskManager
            ->shouldReceive('send')
            ->once()
            ->with(Mockery::type(QueueStatusAction::class))
            ->andReturn($mockResponse);

        Log::shouldReceive('info')
            ->once()
            ->with('Queue status retrieved successfully', Mockery::any());

        $result = $this->service->memberExists('support', 'SIP/1001');

        $this->assertTrue($result);
    }

    public function testMemberExistsFalse()
    {
        $mockResponse = $this->createMockResponse(true, 'Success', [
            'members' => [
                ['interface' => 'SIP/1002', 'name' => 'Jane Smith'],
            ],
        ]);

        $this->mockAsteriskManager
            ->shouldReceive('send')
            ->once()
            ->with(Mockery::type(QueueStatusAction::class))
            ->andReturn($mockResponse);

        Log::shouldReceive('info')
            ->once()
            ->with('Queue status retrieved successfully', Mockery::any());

        $result = $this->service->memberExists('support', 'SIP/1001');

        $this->assertFalse($result);
    }

    public function testMemberExistsWithInvalidQueueName()
    {
        $this->expectException(\InvalidArgumentException::class);
        $this->expectExceptionMessage('Queue name cannot be empty');

        $this->service->memberExists('', 'SIP/1001');
    }

    public function testMemberExistsWithInvalidInterface()
    {
        $this->expectException(\InvalidArgumentException::class);
        $this->expectExceptionMessage('Interface cannot be empty');

        $this->service->memberExists('support', '');
    }

    public function testActionExecutionException()
    {
        $this->mockAsteriskManager
            ->shouldReceive('send')
            ->once()
            ->andThrow(new ActionExecutionException('Connection failed'));

        Log::shouldReceive('error')
            ->once()
            ->with('Failed to add queue member', Mockery::any());

        $this->expectException(ActionExecutionException::class);
        $this->expectExceptionMessage('Connection failed');

        $this->service->addMember('support', 'SIP/1001', 'John Doe', 0);
    }
}
