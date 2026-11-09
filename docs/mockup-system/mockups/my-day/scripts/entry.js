import {startMockup} from '@mockup-system/runtime/bootstrap.js';
import {mountLifeShell} from '@project/project-assets/life-shell.js';
import {mountMyDay} from './my-day.js';
startMockup({'daily-dashboard': context => { mountLifeShell(context); mountMyDay(context); }});
