<?php

declare(strict_types=1);

namespace Tourze\ClaudeTodoBundle\DataFixtures;

use Doctrine\Bundle\FixturesBundle\Fixture;
use Doctrine\Persistence\ObjectManager;
use Tourze\ClaudeTodoBundle\Entity\TodoTask;
use Tourze\ClaudeTodoBundle\Enum\TaskPriority;
use Tourze\ClaudeTodoBundle\Enum\TaskStatus;

class TodoTaskFixtures extends Fixture
{
    public function load(ObjectManager $manager): void
    {
        $groups = ['frontend', 'backend', 'devops', 'testing'];
        $priorities = [TaskPriority::LOW, TaskPriority::NORMAL, TaskPriority::HIGH];

        foreach ($groups as $groupName) {
            for ($i = 1; $i <= 10; ++$i) {
                $task = new TodoTask();
                $task->setGroupName($groupName);
                $task->setDescription(sprintf(
                    '%s task #%d: %s',
                    ucfirst($groupName),
                    $i,
                    $this->getRandomDescription($groupName)
                ));

                $priority = $priorities[array_rand($priorities)];
                $task->setPriority($priority);

                // 设置不同的状态分布
                if ($i <= 3) {
                    $task->setStatus(TaskStatus::IN_PROGRESS);
                    $task->setExecutedTime(new \DateTimeImmutable(sprintf('-%d hours', random_int(1, 72))));
                    $task->complete();
                    $task->setResult('Task completed successfully');
                } elseif (4 === $i) {
                    $task->setStatus(TaskStatus::IN_PROGRESS);
                    $task->setExecutedTime(new \DateTimeImmutable('-1 hour'));
                } elseif (5 === $i) {
                    $task->setStatus(TaskStatus::IN_PROGRESS);
                    $task->setStatus(TaskStatus::FAILED);
                    $task->setExecutedTime(new \DateTimeImmutable('-2 hours'));
                    $task->setResult('Task failed: ' . $this->getRandomError());
                }
                // 其余保持 PENDING 状态

                $manager->persist($task);

                // 创建引用以便其他 fixtures 使用
                $this->addReference(sprintf('todo-task-%s-%d', $groupName, $i), $task);
            }
        }

        // 创建一些特殊的测试任务
        $specialTasks = [
            ['claude-integration', 'Generate unit tests for UserService', TaskPriority::HIGH],
            ['claude-integration', 'Refactor payment module', TaskPriority::HIGH],
            ['claude-integration', 'Optimize database queries', TaskPriority::NORMAL],
            ['performance', 'Profile API endpoints', TaskPriority::NORMAL],
            ['performance', 'Implement caching strategy', TaskPriority::HIGH],
        ];

        foreach ($specialTasks as $index => [$group, $description, $priority]) {
            $task = new TodoTask();
            $task->setGroupName($group);
            $task->setDescription($description);
            $task->setPriority($priority);

            $manager->persist($task);
            $this->addReference(sprintf('special-task-%d', $index), $task);
        }

        $manager->flush();
    }

    private function getRandomDescription(string $groupName): string
    {
        $descriptions = [
            'frontend' => [
                'Update React components',
                'Fix responsive design issues',
                'Implement dark mode',
                'Add form validation',
                'Optimize bundle size',
            ],
            'backend' => [
                'Create REST API endpoint',
                'Implement authentication',
                'Add database migrations',
                'Fix N+1 query problem',
                'Update Symfony dependencies',
            ],
            'devops' => [
                'Configure CI/CD pipeline',
                'Set up monitoring',
                'Update Docker images',
                'Implement backup strategy',
                'Configure load balancer',
            ],
            'testing' => [
                'Write unit tests',
                'Create integration tests',
                'Set up E2E testing',
                'Increase code coverage',
                'Fix flaky tests',
            ],
        ];

        $groupDescriptions = $descriptions[$groupName] ?? ['Generic task'];

        return $groupDescriptions[array_rand($groupDescriptions)];
    }

    private function getRandomError(): string
    {
        $errors = [
            'Connection timeout',
            'Invalid configuration',
            'Dependency not found',
            'Permission denied',
            'Resource limit exceeded',
        ];

        return $errors[array_rand($errors)];
    }
}
