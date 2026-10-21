import {startMockup} from '@mockup-system/runtime/bootstrap.js';
import {mountLifeShell} from '@project/project-assets/life-shell.js';
import {mountTabletPanel} from '@project/project-assets/tablet-panel.js';
startMockup({'panel': ctx => { mountLifeShell(ctx); mountTabletPanel(ctx); }});
