<x-filament-panels::page>
    <div class="space-y-6">
        <!-- Project Filter -->
        <div class="mb-6">
            <x-filament::section>
                <div class="flex flex-col lg:flex-row items-start lg:items-center justify-between gap-4">
                    <div class="flex-1">
                        <h2 class="text-lg font-medium text-gray-900 dark:text-white">
                            {{ $selectedProject ? $selectedProject->name : __('pages.shared.select_project') }}
                        </h2>
                    </div>

                    <div class="w-full lg:w-auto">
                        <x-filament::input.wrapper>
                            <x-filament::input.select wire:model.live="projectId" class="w-full lg:min-w-[200px]">
                                <option value="">{{ __('pages.shared.select_project') }}</option>
                                @foreach ($projects as $project)
                                    <option value="{{ $project->id }}"
                                        {{ $projectId == $project->id ? 'selected' : '' }}>
                                        {{ $project->name }}
                                    </option>
                                @endforeach
                            </x-filament::input.select>
                        </x-filament::input.wrapper>
                    </div>
                </div>
            </x-filament::section>
        </div>

        @if ($selectedProject)
            <!-- dhtmlxGantt Chart -->
            <div class="bg-white dark:bg-gray-800 rounded-xl shadow">
                <div class="p-4 border-b border-gray-200 dark:border-gray-700">
                    <div class="flex flex-col sm:flex-row items-start sm:items-center justify-between gap-4">
                        <h2 class="text-lg font-medium text-gray-900 dark:text-white">{{ __('pages.ticket_timeline.title') }}</h2>
                        <div class="flex items-center gap-2 text-sm text-gray-500 dark:text-gray-400">
                            <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                                    d="M15 12a3 3 0 11-6 0 3 3 0 016 0z" />
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                                    d="M2.458 12C3.732 7.943 7.523 5 12 5c4.478 0 8.268 2.943 9.542 7-1.274 4.057-5.064 7-9.542 7-4.477 0-8.268-2.943-9.542-7z" />
                            </svg>
                            <span>{{ __('pages.ticket_timeline.read_only') }}</span>
                        </div>
                    </div>
                </div>

                <!-- dhtmlxGantt Container -->
                <div class="w-full">
                    @if (count($this->ganttData['data']) > 0)
                        <div id="gantt_here" style="width:100%; height:600px;"></div>
                    @else
                        <div class="flex flex-col items-center justify-center h-64 text-gray-500 gap-4">
                            <svg class="w-16 h-16" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                                    d="M8 7V3m8 4V3m-9 8h10M5 21h14a2 2 0 002-2V7a2 2 0 00-2-2H5a2 2 0 002 2z" />
                            </svg>
                            <h3 class="text-lg font-medium">{{ __('pages.ticket_timeline.empty.title') }}</h3>
                            <p class="text-sm">{{ __('pages.ticket_timeline.empty.description') }}</p>
                        </div>
                    @endif
                </div>
            </div>
        @else
            <div class="flex flex-col items-center justify-center h-64 text-gray-500 gap-4">
                <svg class="w-16 h-16" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                        d="M8 7V3m8 4V3m-9 8h10M5 21h14a2 2 0 002-2V7a2 2 0 00-2-2H5a2 2 0 002 2z" />
                </svg>
                <h2 class="text-xl font-medium">{{ __('pages.ticket_timeline.no_project.title') }}</h2>
                <p class="text-sm">{{ __('pages.ticket_timeline.no_project.description') }}</p>
            </div>
        @endif
    </div>

    @push('styles')
        <link rel="stylesheet" href="https://cdn.dhtmlx.com/gantt/edge/dhtmlxgantt.css" type="text/css">
        <link rel="stylesheet" href="{{ asset('css/gantt-timeline.css') }}" type="text/css">
        <style>
            /* Today marker line styling */
            .gantt_marker.today {
                background-color: #EF4444 !important;
                /* Red color for today line */
                opacity: 0.8;
                z-index: 10;
            }

            .gantt_marker.today .gantt_marker_content {
                background-color: #EF4444 !important;
                color: white !important;
                font-weight: bold;
                font-size: 12px;
                padding: 2px 6px;
                border-radius: 4px;
                white-space: nowrap;
            }
        </style>
    @endpush

    @push('scripts')
        <script src="https://cdn.dhtmlx.com/gantt/edge/dhtmlxgantt.js"></script>
        <script>
            window.ganttState = window.ganttState || {
                initialized: false,
                currentProjectId: '{{ $projectId }}'
            };

            const ticketTimelineTranslations = @js([
                'columns' => [
                    'task' => __('pages.ticket_timeline.columns.task'),
                    'status' => __('pages.ticket_timeline.columns.status'),
                    'duration' => __('pages.ticket_timeline.columns.duration'),
                ],
                'tooltip' => [
                    'task' => __('pages.ticket_timeline.tooltip.task'),
                    'status' => __('pages.ticket_timeline.tooltip.status'),
                    'duration' => __('pages.ticket_timeline.tooltip.duration'),
                    'progress' => __('pages.ticket_timeline.tooltip.progress'),
                    'start' => __('pages.ticket_timeline.tooltip.start'),
                    'end' => __('pages.ticket_timeline.tooltip.end'),
                    'overdue' => __('pages.ticket_timeline.tooltip.overdue'),
                ],
                'today' => __('pages.ticket_timeline.today'),
                'errors' => [
                    'title' => __('pages.ticket_timeline.error.title'),
                    'description' => __('pages.ticket_timeline.error.description'),
                    'label' => __('pages.ticket_timeline.error.label'),
                    'refresh' => __('pages.ticket_timeline.error.refresh'),
                    'library' => __('pages.ticket_timeline.error.library'),
                    'container' => __('pages.ticket_timeline.error.container'),
                ],
                'units' => [
                    'day' => [
                        'one' => __('pages.shared.units.day.one'),
                        'few' => __('pages.shared.units.day.few'),
                        'many' => __('pages.shared.units.day.many'),
                        'other' => __('pages.shared.units.day.other'),
                    ],
                ],
            ]);

            function formatRussianPlural(count, forms) {
                const mod10 = count % 10;
                const mod100 = count % 100;

                if (mod10 === 1 && mod100 !== 11) {
                    return forms.one;
                }

                if (mod10 >= 2 && mod10 <= 4 && (mod100 < 12 || mod100 > 14)) {
                    return forms.few;
                }

                if (mod10 === 0 || (mod10 >= 5 && mod10 <= 9) || (mod100 >= 11 && mod100 <= 14)) {
                    return forms.many;
                }

                return forms.other;
            }

            function formatDays(count) {
                return formatRussianPlural(count, ticketTimelineTranslations.units.day);
            }

            function getGanttData() {
                return @json($this->ganttData ?? ['data' => [], 'links' => []]);
            }

            function waitForGantt(callback, maxAttempts = 50) {
                let attempts = 0;

                function check() {
                    attempts++;
                    if (typeof gantt !== 'undefined' && gantt.init) {
                        callback();
                    } else if (attempts < maxAttempts) {
                        setTimeout(check, 100);
                    } else {
                        console.error('dhtmlxGantt failed to load after', maxAttempts * 100, 'ms');
                        showErrorMessage(ticketTimelineTranslations.errors.library);
                    }
                }
                check();
            }

            function waitForContainer(callback, maxAttempts = 30) {
                let attempts = 0;

                function check() {
                    attempts++;
                    const container = document.getElementById('gantt_here');
                    if (container && container.offsetParent !== null) {
                        callback();
                    } else if (attempts < maxAttempts) {
                        setTimeout(check, 100);
                    } else {
                        console.error('Gantt container not found or not visible after', maxAttempts * 100, 'ms');
                        showErrorMessage(ticketTimelineTranslations.errors.container);
                    }
                }
                check();
            }

            function showErrorMessage(message = ticketTimelineTranslations.errors.title, detail = '') {
                const container = document.getElementById('gantt_here');
                if (container) {
                    container.innerHTML = `
                        <div class="flex flex-col items-center justify-center h-64 text-gray-500 gap-4">
                            <svg class="w-16 h-16" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 8v4m0 4h.01M21 12a9 9 0 11-18 0 9 9 0 0118 0z" />
                            </svg>
                            <h3 class="text-lg font-medium">${message}</h3>
                            <p class="text-sm">${ticketTimelineTranslations.errors.description}</p>
                            ${detail ? `<p class="text-xs text-gray-400">${ticketTimelineTranslations.errors.label} ${detail}</p>` : ''}
                            <button onclick="location.reload()" class="px-4 py-2 bg-blue-500 text-white rounded hover:bg-blue-600">
                                ${ticketTimelineTranslations.errors.refresh}
                            </button>
                        </div>
                    `;
                }
            }

            function initializeGanttSafely() {
                waitForContainer(() => {
                    waitForGantt(() => {
                        initializeGantt();
                    });
                });
            }

            document.addEventListener('DOMContentLoaded', function() {
                console.log('DOM ready, initializing gantt safely...');
                initializeGanttSafely();

                if (typeof Livewire !== 'undefined') {
                    setupLivewireListeners();
                } else {
                    document.addEventListener('livewire:init', setupLivewireListeners);
                }
            });

            document.addEventListener('livewire:navigated', function() {
                console.log('Livewire navigated, reinitializing gantt...');
                window.ganttState.currentProjectId = '{{ $projectId }}';
                if (window.ganttState.initialized) {
                    try {
                        if (typeof gantt !== 'undefined' && gantt.clearAll) {
                            gantt.clearAll();
                        }
                    } catch (e) {
                        console.warn('Error clearing gantt:', e);
                    }
                    window.ganttState.initialized = false;
                }
                setTimeout(() => {
                    initializeGanttSafely();
                }, 100);
            });

            function setupLivewireListeners() {
                Livewire.on('refreshData', () => {
                    console.log('Refreshing gantt chart...');
                    setTimeout(() => {
                        initializeGanttSafely();
                    }, 100);
                });

                Livewire.on('refreshGanttChart', () => {
                    console.log('Refreshing gantt chart after project selection...');
                    setTimeout(() => {
                        initializeGanttSafely();
                    }, 200);
                });
            }

            function initializeGantt() {
                try {
                    const ganttData = getGanttData();
                    console.log('Initializing with gantt data:', ganttData.data.length, 'tasks');

                    if (!ganttData.data || ganttData.data.length === 0) {
                        console.log('No gantt data available');
                        return;
                    }

                    const container = document.getElementById('gantt_here');
                    if (!container) {
                        console.error('Gantt container not found');
                        throw new Error('Gantt container not found');
                    }

                    if (typeof gantt === 'undefined' || !gantt.init) {
                        throw new Error('dhtmlxGantt library not properly loaded');
                    }

                    try {
                        // ✨ Enable marker plugin for today line
                        gantt.plugins({
                            marker: true
                        });

                        gantt.config.date_format = "%Y-%m-%d %H:%i";
                        gantt.config.xml_date = "%Y-%m-%d %H:%i";

                        gantt.config.scales = [{
                                unit: "month",
                                step: 1,
                                format: "%F %Y"
                            },
                            {
                                unit: "day",
                                step: 1,
                                format: "%j"
                            }
                        ];

                        gantt.config.readonly = true;
                        gantt.config.drag_move = false;
                        gantt.config.drag_resize = false;
                        gantt.config.drag_progress = false;
                        gantt.config.drag_links = false;

                        gantt.config.grid_width = 350;
                        gantt.config.row_height = 40;
                        gantt.config.task_height = 32;
                        gantt.config.bar_height = 24;

                        gantt.config.columns = [
                            {
                                name: "text",
                                label: ticketTimelineTranslations.columns.task,
                                width: 200,
                                tree: true
                            },
                            {
                                name: "status",
                                label: ticketTimelineTranslations.columns.status,
                                width: 100,
                                align: "center"
                            },
                            {
                                name: "duration",
                                label: ticketTimelineTranslations.columns.duration,
                                width: 50,
                                align: "center"
                            }
                        ];

                        gantt.templates.task_class = function(start, end, task) {
                            return task.is_overdue ? "overdue" : "";
                        };

                        gantt.templates.tooltip_text = function(start, end, task) {
                            const daysLabel = formatDays(task.duration);

                            return `<b>${ticketTimelineTranslations.tooltip.task}</b> ${task.text}<br/>
                                    <b>${ticketTimelineTranslations.tooltip.status}</b> ${task.status}<br/>
                                    <b>${ticketTimelineTranslations.tooltip.duration}</b> ${task.duration} ${daysLabel}<br/>
                                    <b>${ticketTimelineTranslations.tooltip.progress}</b> ${Math.round(task.progress * 100)}%<br/>
                                    <b>${ticketTimelineTranslations.tooltip.start}</b> ${gantt.templates.tooltip_date_format(start)}<br/>
                                    <b>${ticketTimelineTranslations.tooltip.end}</b> ${gantt.templates.tooltip_date_format(end)}
                                    ${task.is_overdue ? `<br/><b style="color: #ef4444;">${ticketTimelineTranslations.tooltip.overdue}</b>` : ''}`;
                        };
                    } catch (configError) {
                        console.error('Error configuring gantt:', configError);
                        throw new Error('Failed to configure Gantt chart');
                    }

                    try {
                        if (!window.ganttState.initialized) {
                            gantt.init("gantt_here");
                            window.ganttState.initialized = true;
                            console.log('Gantt initialized for the first time');
                        }
                    } catch (initError) {
                        console.error('Error initializing gantt:', initError);
                        throw new Error('Failed to initialize Gantt chart');
                    }

                    try {
                        gantt.clearAll();

                        if (!Array.isArray(ganttData.data)) {
                            throw new Error('Invalid gantt data format: data must be an array');
                        }


                        const processedData = {
                            data: ganttData.data.map(task => {
                                const convertDate = (dateStr) => {
                                    if (!dateStr) return dateStr;
                                    try {
                                        const parts = dateStr.split(' ');
                                        const datePart = parts[0];
                                        const timePart = parts[1] || '00:00';
                                        const [day, month, year] = datePart.split('-');
                                        return `${year}-${month.padStart(2, '0')}-${day.padStart(2, '0')} ${timePart}`;
                                    } catch (e) {
                                        console.warn('Error converting date:', dateStr, e);
                                        return dateStr;
                                    }
                                };

                                return {
                                    ...task,
                                    start_date: convertDate(task.start_date),
                                    end_date: convertDate(task.end_date)
                                };
                            }),
                            links: ganttData.links || []
                        };

                        for (let i = 0; i < processedData.data.length; i++) {
                            const task = processedData.data[i];
                            if (!task.id || !task.text || !task.start_date || !task.end_date) {
                                console.warn('Invalid task data at index', i, task);
                                continue;
                            }
                        }

                        gantt.parse(processedData);

                        // ✨ Add today marker line
                        const today = new Date();
                        gantt.addMarker({
                            start_date: today,
                            css: "today",
                            text: ticketTimelineTranslations.today
                        });

                        console.log('dhtmlxGantt initialized successfully with', processedData.data.length,
                            'tasks and today marker');

                    } catch (parseError) {
                        console.error('Error parsing gantt data:', parseError);
                        throw new Error('Failed to load Gantt data');
                    }

                } catch (error) {
                    console.error('Error initializing dhtmlxGantt:', error);
                    showErrorMessage(ticketTimelineTranslations.errors.title, error.message || '');
                }
            }
        </script>
    @endpush
</x-filament-panels::page>
