<?php

declare(strict_types=1);

namespace Tourze\ClaudeTodoBundle\Controller\Admin;

use Doctrine\ORM\QueryBuilder;
use EasyCorp\Bundle\EasyAdminBundle\Attribute\AdminCrud;
use EasyCorp\Bundle\EasyAdminBundle\Collection\FieldCollection;
use EasyCorp\Bundle\EasyAdminBundle\Collection\FilterCollection;
use EasyCorp\Bundle\EasyAdminBundle\Config\Action;
use EasyCorp\Bundle\EasyAdminBundle\Config\Actions;
use EasyCorp\Bundle\EasyAdminBundle\Config\Crud;
use EasyCorp\Bundle\EasyAdminBundle\Config\Filters;
use EasyCorp\Bundle\EasyAdminBundle\Controller\AbstractCrudController;
use EasyCorp\Bundle\EasyAdminBundle\Dto\EntityDto;
use EasyCorp\Bundle\EasyAdminBundle\Dto\SearchDto;
use EasyCorp\Bundle\EasyAdminBundle\Field\DateTimeField;
use EasyCorp\Bundle\EasyAdminBundle\Field\IdField;
use EasyCorp\Bundle\EasyAdminBundle\Field\IntegerField;
use EasyCorp\Bundle\EasyAdminBundle\Field\TextareaField;
use EasyCorp\Bundle\EasyAdminBundle\Field\TextField;
use EasyCorp\Bundle\EasyAdminBundle\Filter\ChoiceFilter;
use EasyCorp\Bundle\EasyAdminBundle\Filter\DateTimeFilter;
use EasyCorp\Bundle\EasyAdminBundle\Filter\TextFilter;
use Tourze\ClaudeTodoBundle\Entity\TodoTask;
use Tourze\ClaudeTodoBundle\Enum\TaskPriority;
use Tourze\ClaudeTodoBundle\Enum\TaskStatus;
use Tourze\EasyAdminEnumFieldBundle\Field\EnumField;

/**
 * @extends AbstractCrudController<TodoTask>
 */
#[AdminCrud(routePath: '/claude-todo/task', routeName: 'claude_todo_task')]
final class TodoTaskCrudController extends AbstractCrudController
{
    public static function getEntityFqcn(): string
    {
        return TodoTask::class;
    }

    public function configureCrud(Crud $crud): Crud
    {
        return $crud
            ->setEntityLabelInSingular('Claude TODO任务')
            ->setEntityLabelInPlural('Claude TODO任务管理')
            ->setSearchFields(['groupName', 'description', 'result'])
            ->setDefaultSort(['id' => 'DESC'])
            ->setPaginatorPageSize(50)
            ->setHelp('index', '管理Claude TODO任务，包括待处理、进行中、已完成和失败的任务')
        ;
    }

    public function createIndexQueryBuilder(
        SearchDto $searchDto,
        EntityDto $entityDto,
        FieldCollection $fields,
        FilterCollection $filters,
    ): QueryBuilder {
        $qb = parent::createIndexQueryBuilder($searchDto, $entityDto, $fields, $filters);

        // 按优先级和创建时间排序
        $qb->addOrderBy('entity.priority', 'DESC')
            ->addOrderBy('entity.createdTime', 'DESC')
        ;

        return $qb;
    }

    public function configureActions(Actions $actions): Actions
    {
        return $actions
            ->add(Crud::PAGE_INDEX, Action::DETAIL)
        ;
    }

    public function configureFields(string $pageName): iterable
    {
        // 只在索引页面显示ID字段
        if (Crud::PAGE_INDEX === $pageName) {
            yield IdField::new('id', 'ID');
        }

        yield TextField::new('groupName', '任务分组')
            ->setRequired(true)
            ->setColumns(6)
            ->setHelp('用于将相关任务归类管理')
        ;

        yield TextareaField::new('description', '任务描述')
            ->setRequired(true)
            ->setColumns(12)
            ->setHelp('详细描述要执行的任务内容')
            ->hideOnIndex()
        ;

        // 在列表页显示截断的描述
        if (Crud::PAGE_INDEX === $pageName) {
            yield TextField::new('description', '任务描述')
                ->formatValue(function ($value) {
                    if (mb_strlen($value) > 80) {
                        return mb_substr($value, 0, 80) . '...';
                    }

                    return $value;
                })
                ->setColumns(4)
            ;
        }

        $statusField = EnumField::new('status', '状态');
        $statusField->setEnumCases(TaskStatus::cases());
        $statusField->setColumns(3);
        $statusField->setRequired(true);
        $statusField->setHelp('任务的当前状态');
        $statusField->setFormTypeOption('choice_value', 'value');
        $statusField->formatValue(function ($value) {
            return $value instanceof TaskStatus ? $value->getLabel() : $value;
        });
        yield $statusField;

        $priorityField = EnumField::new('priority', '优先级');
        $priorityField->setEnumCases(TaskPriority::cases());
        $priorityField->setColumns(3);
        $priorityField->setRequired(true);
        $priorityField->setHelp('任务的优先级设置');
        $priorityField->setFormTypeOption('choice_value', 'value');
        $priorityField->formatValue(function ($value) {
            return $value instanceof TaskPriority ? $value->getLabel() : $value;
        });
        yield $priorityField;

        yield DateTimeField::new('createdTime', '创建时间')
            ->setFormat('yyyy-MM-dd HH:mm:ss')
            ->onlyOnIndex()
        ;

        yield DateTimeField::new('updatedTime', '更新时间')
            ->setFormat('yyyy-MM-dd HH:mm:ss')
            ->hideOnForm()
            ->hideOnIndex()
        ;

        yield DateTimeField::new('executedTime', '执行时间')
            ->setFormat('yyyy-MM-dd HH:mm:ss')
            ->hideOnForm()
            ->hideOnIndex()
        ;

        yield DateTimeField::new('completedTime', '完成时间')
            ->setFormat('yyyy-MM-dd HH:mm:ss')
            ->hideOnForm()
            ->onlyOnDetail()
        ;

        yield TextareaField::new('result', '执行结果')
            ->setColumns(12)
            ->hideOnIndex()
            ->hideOnForm()
            ->setHelp('任务执行后的结果信息')
        ;

        yield IntegerField::new('version', '版本号')
            ->onlyOnDetail()
            ->setHelp('用于乐观锁控制')
        ;
    }

    public function configureFilters(Filters $filters): Filters
    {
        return $filters
            ->add(TextFilter::new('groupName', '任务分组'))
            ->add(TextFilter::new('description', '任务描述'))
            ->add(ChoiceFilter::new('status', '状态')
                ->setChoices([
                    '待处理' => TaskStatus::PENDING->value,
                    '进行中' => TaskStatus::IN_PROGRESS->value,
                    '已完成' => TaskStatus::COMPLETED->value,
                    '失败' => TaskStatus::FAILED->value,
                ])
            )
            ->add(ChoiceFilter::new('priority', '优先级')
                ->setChoices([
                    '低' => TaskPriority::LOW->value,
                    '普通' => TaskPriority::NORMAL->value,
                    '高' => TaskPriority::HIGH->value,
                ])
            )
            ->add(DateTimeFilter::new('createdTime', '创建时间'))
            ->add(DateTimeFilter::new('completedTime', '完成时间'))
        ;
    }
}
