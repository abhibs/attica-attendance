@php
    $payload = $candidate->hiring_payload ?? [];
    $qualifications = data_get($payload, 'qualifications', []);
    $workExperiences = data_get($payload, 'work_experiences', []);
    $references = data_get($payload, 'references', []);
@endphp
<!doctype html>
<html lang="en">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>Attica Hiring Verification</title>
    <link href="{{ asset('public/admin/assets/css/bootstrap.min.css') }}" rel="stylesheet">
    <style>
        body { background: #f6f7fb; }
        .verify-shell { max-width: 1100px; margin: 2rem auto; }
        .verify-card { border: 0; border-radius: 1rem; box-shadow: 0 12px 30px rgba(0,0,0,.06); }
        .verify-photo { max-height: 220px; width: 100%; object-fit: cover; border-radius: 1rem; }
    </style>
</head>
<body>
    <div class="container verify-shell">
        @if (session('status'))
            <div class="alert alert-success">{{ session('status') }}</div>
        @endif

        <div class="card verify-card mb-4">
            <div class="card-body p-4">
                <div class="row g-4">
                    <div class="col-lg-8">
                        <h2 class="mb-1">Attica Hiring Verification</h2>
                        <p class="text-muted mb-3">Please review the information below and verify that it is correct.</p>
                        <div class="row g-3">
                            <div class="col-md-6"><strong>Position Applied For:</strong> {{ $candidate->position_applied_for ?: '--' }}</div>
                            <div class="col-md-6"><strong>Candidate Name:</strong> {{ $candidate->candidate_name ?: '--' }}</div>
                            <div class="col-md-6"><strong>Contact Number:</strong> {{ $candidate->contact_number ?: '--' }}</div>
                            <div class="col-md-6"><strong>Email:</strong> {{ $candidate->email ?: '--' }}</div>
                            <div class="col-md-6"><strong>Aadhaar Number:</strong> {{ $candidate->aadhaar_number ?: data_get($payload, 'aadhaar_number') ?: '--' }}</div>
                            <div class="col-md-6"><strong>Date of Birth:</strong> {{ data_get($payload, 'date_of_birth') ?: '--' }}</div>
                            <div class="col-md-6"><strong>Gender:</strong> {{ data_get($payload, 'gender') ?: '--' }}</div>
                            <div class="col-md-6"><strong>Marital Status:</strong> {{ data_get($payload, 'marital_status') ?: '--' }}</div>
                            <div class="col-md-6"><strong>Notice Period:</strong> {{ data_get($payload, 'notice_period') ?: '--' }}</div>
                        </div>
                    </div>
                    <div class="col-lg-4">
                        @if ($candidate->candidate_photo_path)
                            <img src="{{ asset($candidate->candidate_photo_path) }}" alt="{{ $candidate->candidate_name }}" class="verify-photo">
                        @else
                            <div class="border rounded-4 h-100 d-flex align-items-center justify-content-center text-muted bg-light">No candidate photo captured</div>
                        @endif
                    </div>
                </div>
            </div>
        </div>

        <div class="card verify-card mb-4">
            <div class="card-body p-4">
                <h4 class="mb-3">Declaration Details</h4>
                <div class="row g-4">
                    <div class="col-md-6">
                        <h6>Addresses</h6>
                        <p class="mb-2"><strong>Current Address:</strong><br>{{ data_get($payload, 'current_address') ?: '--' }}</p>
                        <p class="mb-0"><strong>Permanent Address:</strong><br>{{ data_get($payload, 'permanent_address') ?: '--' }}</p>
                    </div>
                    <div class="col-md-6">
                        <h6>Additional Information</h6>
                        <p class="mb-2"><strong>Physical Fitness:</strong> {{ data_get($payload, 'physical_fitness') === null ? '--' : (data_get($payload, 'physical_fitness') ? 'Yes' : 'No') }}</p>
                        <p class="mb-2"><strong>Reason:</strong> {{ data_get($payload, 'physical_fitness_reason') ?: '--' }}</p>
                        <p class="mb-2"><strong>Own Two Wheeler:</strong> {{ data_get($payload, 'own_two_wheeler') === null ? '--' : (data_get($payload, 'own_two_wheeler') ? 'Yes' : 'No') }}</p>
                        <p class="mb-0"><strong>How do you know Attica:</strong> {{ data_get($payload, 'know_attica') ?: '--' }}</p>
                    </div>
                </div>
            </div>
        </div>

        <div class="card verify-card mb-4">
            <div class="card-body p-4">
                <h4 class="mb-3">Professional Qualification</h4>
                <div class="table-responsive">
                    <table class="table table-bordered">
                        <thead>
                            <tr>
                                <th>Examination</th>
                                <th>University</th>
                                <th>Main Subject</th>
                                <th>Year of Passing</th>
                                <th>Percentage Obtained</th>
                            </tr>
                        </thead>
                        <tbody>
                            @forelse ($qualifications as $row)
                                <tr>
                                    <td>{{ $row['examination'] ?: '--' }}</td>
                                    <td>{{ $row['university'] ?: '--' }}</td>
                                    <td>{{ $row['main_subject'] ?: '--' }}</td>
                                    <td>{{ $row['year_of_passing'] ?: '--' }}</td>
                                    <td>{{ $row['percentage_obtained'] ?: '--' }}</td>
                                </tr>
                            @empty
                                <tr><td colspan="5" class="text-center text-muted">No qualification rows added.</td></tr>
                            @endforelse
                        </tbody>
                    </table>
                </div>
            </div>
        </div>

        <div class="card verify-card mb-4">
            <div class="card-body p-4">
                <h4 class="mb-3">Skills, Experience & References</h4>
                <div class="row g-4">
                    <div class="col-lg-6">
                        <p><strong>Computer Knowledge:</strong><br>{{ data_get($payload, 'computer_knowledge') ?: '--' }}</p>
                        <p><strong>Languages - Speak:</strong><br>{{ data_get($payload, 'languages_speak') ?: '--' }}</p>
                        <p><strong>Languages - Read:</strong><br>{{ data_get($payload, 'languages_read') ?: '--' }}</p>
                        <p class="mb-0"><strong>Languages - Write:</strong><br>{{ data_get($payload, 'languages_write') ?: '--' }}</p>
                    </div>
                    <div class="col-lg-6">
                        <p><strong>Present Remuneration:</strong> {{ data_get($payload, 'present_remuneration') ?: '--' }}</p>
                        <p><strong>Salary Expectation:</strong> {{ data_get($payload, 'salary_expectation') ?: '--' }}</p>
                        <p><strong>Preferred Work Location:</strong> {{ data_get($payload, 'preferred_work_location_label') ?: '--' }}</p>
                        <p><strong>Place:</strong> {{ data_get($payload, 'place') ?: '--' }}</p>
                        <p class="mb-0"><strong>Signature:</strong> {{ data_get($payload, 'signature') ?: '--' }}</p>
                    </div>
                </div>

                <hr class="my-4">

                <h6>Work Experience</h6>
                <div class="table-responsive mb-4">
                    <table class="table table-bordered">
                        <thead>
                            <tr>
                                <th>Company Name</th>
                                <th>Designation</th>
                                <th>Experience</th>
                            </tr>
                        </thead>
                        <tbody>
                            @forelse ($workExperiences as $row)
                                <tr>
                                    <td>{{ $row['company_name'] ?: '--' }}</td>
                                    <td>{{ $row['designation'] ?: '--' }}</td>
                                    <td>{{ $row['experience'] ?: '--' }}</td>
                                </tr>
                            @empty
                                <tr><td colspan="3" class="text-center text-muted">No work experience rows added.</td></tr>
                            @endforelse
                        </tbody>
                    </table>
                </div>

                <h6>References</h6>
                <div class="table-responsive">
                    <table class="table table-bordered">
                        <thead>
                            <tr>
                                <th>Name</th>
                                <th>Contact Number</th>
                                <th>Designation</th>
                                <th>Relationship</th>
                            </tr>
                        </thead>
                        <tbody>
                            @forelse ($references as $row)
                                <tr>
                                    <td>{{ $row['name'] ?: '--' }}</td>
                                    <td>{{ $row['contact_number'] ?: '--' }}</td>
                                    <td>{{ $row['designation'] ?: '--' }}</td>
                                    <td>{{ $row['relationship'] ?: '--' }}</td>
                                </tr>
                            @empty
                                <tr><td colspan="4" class="text-center text-muted">No reference rows added.</td></tr>
                            @endforelse
                        </tbody>
                    </table>
                </div>
            </div>
        </div>

        <div class="card verify-card">
            <div class="card-body p-4">
                @if ($candidate->verified_at)
                    <div class="alert alert-success mb-0">
                        This form was verified on {{ $candidate->verified_at->format('d-m-Y H:i') }}.
                    </div>
                @else
                    <form method="post" action="{{ route('recruitment-verification-submit', $candidate->public_token) }}">
                        @csrf
                        <p class="mb-3">If all the above information is correct, please confirm verification.</p>
                        <button type="submit" class="btn btn-primary btn-lg">I Verify This Information</button>
                    </form>
                @endif
            </div>
        </div>
    </div>
</body>
</html>
