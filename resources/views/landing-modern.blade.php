@php
    $employeeCount = $employeeCount ?? 0;
    $managerCount = $managerCount ?? 0;
@endphp

<x-layouts.portal-vtb
    title="VTB Taxi"
    heading="Цифровой контур заявок на корпоративное такси"
    subheading="Внутренняя рабочая система: заявки, очередь согласования, сотрудники, аудит, CSV-выгрузки."
>
    <section class="page-stack">
        <section class="panel panel--compact" data-reveal>
            <div class="panel-header panel-header--tight">
                <div>
                    <span class="kicker">Быстрый старт</span>
                    <h2>Что делать в первую очередь</h2>
                    <p>Главная цель системы: быстро создавать заявки и так же быстро обрабатывать очередь согласования.</p>
                </div>
            </div>

            <div class="ops-kpi-strip ops-kpi-strip--stack" aria-label="Короткая сводка">
                <span class="ops-badge">Сотрудники: {{ $employeeCount }}</span>
                <span class="ops-badge">Руководители: {{ $managerCount }}</span>
                <span class="ops-badge ops-badge--approved">Экспорт: CSV</span>
            </div>

            <div class="ops-rulelist" aria-label="Сценарий работы">
                <div class="ops-rule">
                    <strong>Сотрудник</strong>
                    <span>Создаёт заявку в разделе «Мои заявки» и отслеживает статус.</span>
                </div>
                <div class="ops-rule">
                    <strong>Руководитель</strong>
                    <span>Обрабатывает очередь (массово/выборочно), финализирует и выгружает CSV.</span>
                </div>
                <div class="ops-rule">
                    <strong>Администратор</strong>
                    <span>Смотрит аудит и управляет доступами.</span>
                </div>
            </div>

            <div class="form-actions">
                <a href="{{ route('login') }}" class="button">Войти</a>
                <a href="{{ route('about') }}" class="button-ghost">О проекте</a>
                <a href="{{ route('privacy') }}" class="button-ghost">Политика ПДн</a>
            </div>
        </section>

        <section class="panel panel--compact" data-reveal>
            <div class="panel-header panel-header--tight">
                <div>
                    <span class="kicker">Разделы</span>
                    <h2>Навигация отражает задачи</h2>
                    <p>Без “витринных” блоков: только рабочие экраны и действия.</p>
                </div>
            </div>

            <div class="info-table info-table--scroll" role="table" aria-label="Разделы системы">
                <div class="info-table__head" role="row">
                    <span role="columnheader">Раздел</span>
                    <span role="columnheader">Задача</span>
                    <span role="columnheader">Ключевое действие</span>
                </div>
                <div class="info-row" role="row">
                    <div role="cell"><strong>Мои заявки</strong></div>
                    <div role="cell">Создать поездку и увидеть статус.</div>
                    <div role="cell">Отправить / отменить (пока “На согласовании”).</div>
                </div>
                <div class="info-row" role="row">
                    <div role="cell"><strong>Панель руководителя</strong></div>
                    <div role="cell">Проверить очередь и обработать поток.</div>
                    <div role="cell">Массовое одобрение, перенос в финальные.</div>
                </div>
                <div class="info-row" role="row">
                    <div role="cell"><strong>Сотрудники</strong></div>
                    <div role="cell">Доступы и приглашения.</div>
                    <div role="cell">Создать приглашение / удалить доступ.</div>
                </div>
                <div class="info-row" role="row">
                    <div role="cell"><strong>Аудит</strong></div>
                    <div role="cell">Проверить, кто что сделал.</div>
                    <div role="cell">Фильтры + экспорт журнала.</div>
                </div>
            </div>
        </section>
    </section>
</x-layouts.portal-vtb>
