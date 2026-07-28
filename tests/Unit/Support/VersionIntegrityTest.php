<?php

namespace Tests\Unit\Support;

use App\Support\VersionIntegrity;
use Illuminate\Support\Facades\Process;
use Tests\TestCase;

/**
 * verifyAgainstGit() builds its own disposable git repo per test rather than
 * asserting against this project's own tag history — hermetic, and immune
 * to this repo's actual tags changing/getting pruned over time.
 */
class VersionIntegrityTest extends TestCase
{
    private string $repo;

    protected function setUp(): void
    {
        parent::setUp();

        if (! Process::run(['git', '--version'])->successful()) {
            $this->markTestSkipped('git is not available in this environment.');
        }

        $this->repo = sys_get_temp_dir().'/version-integrity-test-'.uniqid();
        mkdir($this->repo);
        $this->git(['init', '-q']);
        $this->git(['config', 'user.email', 'test@example.com']);
        $this->git(['config', 'user.name', 'Test']);
        file_put_contents($this->repo.'/VERSION', "1.0.0\n");
        $this->git(['add', '.']);
        $this->git(['commit', '-q', '-m', 'initial']);
        $this->git(['tag', 'v1.0.0']);
    }

    protected function tearDown(): void
    {
        Process::run(['rm', '-rf', $this->repo]);
        parent::tearDown();
    }

    private function git(array $args): void
    {
        Process::path($this->repo)->run(array_merge(['git'], $args))->throw();
    }

    private function headCommit(): string
    {
        return trim(Process::path($this->repo)->run(['git', 'rev-parse', 'HEAD'])->output());
    }

    // ── isValidFormat() — pure string check, no git involved ──────────────

    public function test_valid_formats_are_accepted(): void
    {
        $this->assertTrue(VersionIntegrity::isValidFormat('1.3.3'));
        $this->assertTrue(VersionIntegrity::isValidFormat('0.0.1'));
        $this->assertTrue(VersionIntegrity::isValidFormat('2.0.0-beta.1'));
    }

    public function test_invalid_formats_are_rejected(): void
    {
        $this->assertFalse(VersionIntegrity::isValidFormat('unknown'));
        $this->assertFalse(VersionIntegrity::isValidFormat(''));
        $this->assertFalse(VersionIntegrity::isValidFormat('v1.3.3')); // no leading 'v' — that's a git tag convention, not the stored value
        $this->assertFalse(VersionIntegrity::isValidFormat('1.3'));
        $this->assertFalse(VersionIntegrity::isValidFormat('not a version at all'));
    }

    // ── verifyAgainstGit() ──────────────────────────────────────────────

    public function test_head_at_the_tagged_commit_verifies_true(): void
    {
        $this->assertTrue(VersionIntegrity::verifyAgainstGit('1.0.0', $this->repo));
    }

    public function test_head_ahead_of_the_tagged_commit_still_verifies_true(): void
    {
        // The normal "a few commits into dev since the last release, VERSION
        // hasn't been bumped yet" case — not tampering, must not be flagged.
        file_put_contents($this->repo.'/other.txt', 'x');
        $this->git(['add', '.']);
        $this->git(['commit', '-q', '-m', 'unrelated follow-up commit']);

        $this->assertTrue(VersionIntegrity::verifyAgainstGit('1.0.0', $this->repo));
    }

    public function test_a_version_with_no_matching_tag_anywhere_verifies_false(): void
    {
        $this->assertFalse(VersionIntegrity::verifyAgainstGit('9.9.9', $this->repo));
    }

    public function test_head_behind_the_tagged_commit_verifies_false(): void
    {
        // VERSION claims a release that isn't actually on disk: tag a LATER
        // commit as v2.0.0, then roll HEAD back before it.
        $firstCommit = $this->headCommit();
        file_put_contents($this->repo.'/other.txt', 'x');
        $this->git(['add', '.']);
        $this->git(['commit', '-q', '-m', 'a later commit']);
        $this->git(['tag', 'v2.0.0']);
        $this->git(['checkout', '-q', $firstCommit]);

        $this->assertFalse(VersionIntegrity::verifyAgainstGit('2.0.0', $this->repo));
    }

    public function test_no_git_directory_is_unverifiable_not_false(): void
    {
        $noGitDir = sys_get_temp_dir().'/no-git-'.uniqid();
        mkdir($noGitDir);

        // null ("can't check") must stay distinct from false ("checked, and
        // it's wrong") — a zip-uploaded shared-hosting install with no git
        // history is the normal case here, not a tamper signal.
        $this->assertNull(VersionIntegrity::verifyAgainstGit('1.0.0', $noGitDir));

        rmdir($noGitDir);
    }

    public function test_an_invalid_format_is_rejected_without_shelling_out_to_git(): void
    {
        $this->assertFalse(VersionIntegrity::verifyAgainstGit('not-a-version', $this->repo));
    }
}
