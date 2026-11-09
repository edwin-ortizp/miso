import {mountTaskCalendar} from './task-calendar.js';
import {mountTaskCategoryFilter} from './category-filter.js';
import {startMockup} from '@mockup-system/runtime/bootstrap.js';
import {mountLifeShell} from '@project/project-assets/life-shell.js';
import {mountLogSort} from '@project/project-assets/log-sort.js';
startMockup({'tasks-categories': mountLifeShell, 'tasks-list': context => { mountLifeShell(context); mountLogSort(context, 'tasks'); mountTaskCategoryFilter(context); }, 'tasks-calendar': context => { mountLifeShell(context); mountTaskCalendar(context); }, 'tasks-kanban': mountLifeShell, 'tasks-planning': mountLifeShell});
