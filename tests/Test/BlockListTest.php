<?php

declare(strict_types=1);

namespace Test;

use PHPUnit\Framework\TestCase;
use Phluxor\Remote\BlockList;

class BlockListTest extends TestCase
{
    private BlockList $blockList;

    protected function setUp(): void
    {
        $this->blockList = new BlockList();
    }

    public function testInitialBlockedMembersIsEmpty(): void
    {
        $set = $this->blockList->blockedMembers();
        $this->assertEquals(0, $set->size());
    }

    public function testBlockAddsMembersToBlockList(): void
    {
        $memberID = 'user1';
        $this->blockList->block($memberID);
        $this->assertTrue($this->blockList->isBlocked($memberID));
    }

    public function testIsBlockedReturnsFalseForNonBlockedMembers(): void
    {
        $nonBlockedMemberID = 'user2';
        $this->assertFalse($this->blockList->isBlocked($nonBlockedMemberID));
    }

    public function testBlockMultipleMembers(): void
    {
        $members = ['user1', 'user2', 'user3'];
        $this->blockList->block(...$members);
        foreach ($members as $member) {
            $this->assertTrue($this->blockList->isBlocked($member));
        }
    }

    public function testLenReturnsCorrectNumberOfBlockedMembers(): void
    {
        $this->assertEquals(0, $this->blockList->len());
        $this->blockList->block('user1', 'user2');
        $this->assertEquals(2, $this->blockList->len());
    }
}
