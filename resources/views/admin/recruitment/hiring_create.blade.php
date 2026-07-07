@extends('admin.layout.app')

@section('content')
    @php
        $existingGroups = old('question_groups', [['position' => '', 'questions' => implode(PHP_EOL, $genericQuestions)]]);
        $isWalkinForm = (string) old('is_walkin_form', '0') === '1';
    @endphp

    <div class="main-content">
        <style>
            .question-bank-card {
                border: 1px solid rgba(0, 0, 0, 0.08);
                border-radius: 0.9rem;
                padding: 1rem;
                background: #fff;
            }

            .question-bank-card + .question-bank-card {
                margin-top: 1rem;
            }
        </style>

        <div class="page-breadcrumb d-none d-sm-flex align-items-center mb-3">
            <div class="breadcrumb-title pe-3">Recruitment</div>
            <div class="ps-3">
                <nav aria-label="breadcrumb">
                    <ol class="breadcrumb mb-0 p-0">
                        <li class="breadcrumb-item"><a href="{{ route('admin-dashboard') }}"><i class="bx bx-home-alt"></i></a></li>
                        <li class="breadcrumb-item"><a href="{{ route('admin-hiring-index') }}">Hiring</a></li>
                        <li class="breadcrumb-item active" aria-current="page">Create Static Hiring Form</li>
                    </ol>
                </nav>
            </div>
        </div>

        @if ($errors->any())
            <div class="alert alert-danger border-0">
                <ul class="mb-0">
                    @foreach ($errors->all() as $error)
                        <li>{{ $error }}</li>
                    @endforeach
                </ul>
            </div>
        @endif

        <div class="card rounded-4">
            <div class="card-body">
                <form method="post" action="{{ route('admin-hiring-store') }}">
                    @csrf

                    <div class="mb-4">
                        <h4 class="mb-1">Create Static Hiring Form</h4>
                        <p class="mb-0 text-muted">This creates one reusable hiring link. Share it with any number of candidates. Each candidate submission will create a unique submission ID and a separate row in the hiring table.</p>
                    </div>

                    <div class="row g-3 mb-4">
                        <div class="col-md-6">
                            <label class="form-label">Form Title</label>
                            <input type="text" name="title" class="form-control" value="{{ old('title', 'Attica Hiring Form') }}" required>
                        </div>
                        <div class="col-md-3">
                            <label class="form-label">Hiring Date</label>
                            <input type="date" name="hiring_date" class="form-control" value="{{ old('hiring_date', now()->toDateString()) }}" required>
                        </div>
                        <div class="col-md-3">
                            <label class="form-label d-block">Form Type</label>
                            <div class="border rounded-3 p-2 mt-2">
                                <div class="form-check mb-2">
                                    <input class="form-check-input" type="radio" name="is_walkin_form" value="0" id="isStandardForm" @checked(! $isWalkinForm)>
                                    <label class="form-check-label" for="isStandardForm">
                                        Standard hiring form
                                    </label>
                                </div>
                                <div class="form-check">
                                    <input class="form-check-input" type="radio" name="is_walkin_form" value="1" id="isWalkinForm" @checked($isWalkinForm)>
                                    <label class="form-check-label" for="isWalkinForm">
                                        Walk-in form without video interview
                                    </label>
                                </div>
                            </div>
                        </div>
                    </div>

                    <div class="d-flex flex-wrap justify-content-between align-items-center gap-2 mb-3">
                        <div>
                            <h5 class="mb-1">Interview Question Bank</h5>
                            <p class="mb-0 text-muted">Add one position per block. Enter one question per line. Standard hiring forms use these questions for the video interview. Walk-in forms can skip interview questions if you only need a simple submission link.</p>
                        </div>
                        <button type="button" class="btn btn-outline-primary" data-add-question-group>Add Position</button>
                    </div>

                    <div data-question-group-rows>
                        @foreach ($existingGroups as $index => $group)
                            <div class="question-bank-card" data-question-group-row>
                                <div class="d-flex flex-wrap justify-content-between align-items-center gap-2 mb-3">
                                    <h6 class="mb-0" data-row-title>Position {{ $index + 1 }}</h6>
                                    <button type="button" class="btn btn-sm btn-outline-danger" data-remove-question-group>Remove</button>
                                </div>

                                <div class="row g-3">
                                    <div class="col-md-4">
                                        <label class="form-label">Position Name</label>
                                        <input type="text" name="question_groups[{{ $index }}][position]" class="form-control" value="{{ data_get($group, 'position') }}" placeholder="Example: Sales Executive">
                                    </div>
                                    <div class="col-md-8">
                                        <label class="form-label">Questions</label>
                                        <textarea name="question_groups[{{ $index }}][questions]" class="form-control" rows="8" placeholder="Enter one question per line">{{ data_get($group, 'questions') }}</textarea>
                                        <div class="form-text">Minimum 5 questions if you configure a position. Leave the whole block blank to skip custom questions for that position.</div>
                                    </div>
                                </div>
                            </div>
                        @endforeach
                    </div>

                    <div class="mt-4 d-flex flex-wrap gap-2">
                        <button type="submit" class="btn btn-primary px-5">Create Hiring Link</button>
                        <a href="{{ route('admin-hiring-index') }}" class="btn btn-outline-secondary">Back</a>
                    </div>
                </form>
            </div>
        </div>
    </div>

    <template id="question-group-template">
        <div class="question-bank-card" data-question-group-row>
            <div class="d-flex flex-wrap justify-content-between align-items-center gap-2 mb-3">
                <h6 class="mb-0" data-row-title>Position</h6>
                <button type="button" class="btn btn-sm btn-outline-danger" data-remove-question-group>Remove</button>
            </div>

            <div class="row g-3">
                <div class="col-md-4">
                    <label class="form-label">Position Name</label>
                    <input type="text" name="question_groups[__INDEX__][position]" class="form-control" placeholder="Example: Sales Executive">
                </div>
                <div class="col-md-8">
                    <label class="form-label">Questions</label>
                    <textarea name="question_groups[__INDEX__][questions]" class="form-control" rows="8" placeholder="Enter one question per line"></textarea>
                    <div class="form-text">Minimum 5 questions if you configure a position.</div>
                </div>
            </div>
        </div>
    </template>

    <script>
        document.addEventListener('DOMContentLoaded', function () {
            const rowsContainer = document.querySelector('[data-question-group-rows]');
            const template = document.getElementById('question-group-template');

            function syncRows() {
                const rows = rowsContainer.querySelectorAll('[data-question-group-row]');

                rows.forEach(function (row, index) {
                    row.querySelector('[data-row-title]').textContent = `Position ${index + 1}`;

                    row.querySelectorAll('input[name^="question_groups["], textarea[name^="question_groups["]').forEach(function (input) {
                        if (input.name.includes('[position]')) {
                            input.name = `question_groups[${index}][position]`;
                        }

                        if (input.name.includes('[questions]')) {
                            input.name = `question_groups[${index}][questions]`;
                        }
                    });
                });
            }

            function addRow() {
                const index = rowsContainer.querySelectorAll('[data-question-group-row]').length;
                rowsContainer.insertAdjacentHTML('beforeend', template.innerHTML.replaceAll('__INDEX__', index));
                syncRows();
            }

            document.addEventListener('click', function (event) {
                const addButton = event.target.closest('[data-add-question-group]');
                if (addButton) {
                    addRow();
                    return;
                }

                const removeButton = event.target.closest('[data-remove-question-group]');
                if (!removeButton) {
                    return;
                }

                removeButton.closest('[data-question-group-row]')?.remove();

                if (!rowsContainer.querySelector('[data-question-group-row]')) {
                    addRow();
                } else {
                    syncRows();
                }
            });

            syncRows();
        });
    </script>
@endsection