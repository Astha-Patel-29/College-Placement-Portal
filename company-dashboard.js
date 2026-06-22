document.addEventListener('DOMContentLoaded', function () {
    var authGate = document.getElementById('auth-gate');
    var appRoot = document.getElementById('app-root');
    var welcomeHeading = document.getElementById('welcome-heading');
    var companyProfile = document.getElementById('company-profile');
    var statJobs = document.getElementById('stat-jobs');
    var statApps = document.getElementById('stat-apps');
    var jobsEmpty = document.getElementById('jobs-empty');
    var jobsTableWrap = document.getElementById('jobs-table-wrap');
    var jobsTbody = document.getElementById('jobs-tbody');
    var postForm = document.getElementById('post-job-form');
    var postMsg = document.getElementById('post-msg');
    var postSubmit = document.getElementById('post-submit');
    var modal = document.getElementById('applicants-modal');
    var modalTitle = document.getElementById('modal-title');
    var modalBody = document.getElementById('modal-body');
    var modalClose = document.getElementById('modal-close');
    var logoutLink = document.querySelector('a[href="logout.php"]');

    var rawUser = localStorage.getItem('currentUser');
    var currentUser = null;

    try {
        currentUser = rawUser ? JSON.parse(rawUser) : null;
    } catch (error) {
        currentUser = null;
    }

    if (!currentUser || String(currentUser.role || '').toLowerCase() !== 'company') {
        showAuthGate();
        return;
    }

    if (logoutLink) {
        logoutLink.addEventListener('click', function (event) {
            event.preventDefault();
            localStorage.removeItem('currentUser');
            fetch('logout.php', { method: 'POST' }).catch(function () {});
            window.location.href = 'login.html';
        });
    }

    if (modalClose) {
        modalClose.addEventListener('click', closeModal);
    }

    if (modal) {
        modal.addEventListener('click', function (event) {
            if (event.target === modal) {
                closeModal();
            }
        });
    }

    if (postForm) {
        postForm.addEventListener('submit', function (event) {
            event.preventDefault();
            submitJob();
        });
    }

    if (jobsTbody) {
        jobsTbody.addEventListener('click', function (event) {
            var button = event.target.closest('button[data-job-id]');
            if (!button) {
                return;
            }
            loadApplicants(button.getAttribute('data-job-id'), button.getAttribute('data-job-title') || 'Applicants');
        });
    }

    loadDashboard();

    function showAuthGate() {
        if (appRoot) {
            appRoot.style.display = 'none';
        }
        if (authGate) {
            authGate.style.display = 'block';
        }
    }

    function showApp() {
        if (authGate) {
            authGate.style.display = 'none';
        }
        if (appRoot) {
            appRoot.style.display = 'block';
        }
    }

    function escapeHtml(value) {
        return String(value == null ? '' : value)
            .replace(/&/g, '&amp;')
            .replace(/</g, '&lt;')
            .replace(/>/g, '&gt;')
            .replace(/"/g, '&quot;')
            .replace(/'/g, '&#39;');
    }

    function formatLabel(value) {
        return String(value || '')
            .replace(/_/g, ' ')
            .replace(/\b\w/g, function (ch) { return ch.toUpperCase(); });
    }

    function showPostMessage(type, text) {
        if (!postMsg) {
            return;
        }
        if (!text) {
            postMsg.innerHTML = '';
            return;
        }
        postMsg.innerHTML = '<div class="msg ' + (type === 'ok' ? 'ok' : 'err') + '">' + escapeHtml(text) + '</div>';
    }

    function fetchJson(url, options) {
        return fetch(url, options).then(function (response) {
            return response.text().then(function (text) {
                var data = null;
                try {
                    data = text ? JSON.parse(text) : null;
                } catch (error) {
                    throw new Error(text || 'Invalid server response');
                }

                if (!response.ok || !data || data.success === false) {
                    throw new Error((data && data.error) || ('Request failed (' + response.status + ')'));
                }

                return data;
            });
        });
    }

    function renderProfile(company) {
        if (!companyProfile) {
            return;
        }

        var items = [
            ['Name', company.name || currentUser.name || 'Company'],
            ['Email', company.email || currentUser.email || 'Not available'],
            ['Phone', company.phone || 'Not provided'],
            ['Company Name', company.company_name || company.name || 'Not provided'],
            ['Company Type', company.company_type || 'Not provided'],
            ['Joined', company.created_at ? new Date(company.created_at).toLocaleDateString() : 'Not available']
        ];

        companyProfile.innerHTML = items.map(function (item) {
            return '<div><dt>' + escapeHtml(item[0]) + '</dt><dd>' + escapeHtml(item[1]) + '</dd></div>';
        }).join('');

        if (welcomeHeading) {
            welcomeHeading.textContent = 'Welcome, ' + (company.company_name || company.name || 'Company');
        }
    }

    function renderJobs(jobs) {
        if (!jobsTbody || !jobsEmpty || !jobsTableWrap) {
            return;
        }

        if (!jobs || !jobs.length) {
            jobsTbody.innerHTML = '';
            jobsTableWrap.style.display = 'none';
            jobsEmpty.style.display = 'block';
            return;
        }

        jobsEmpty.style.display = 'none';
        jobsTableWrap.style.display = 'block';
        jobsTbody.innerHTML = jobs.map(function (job) {
            var title = escapeHtml(job.title || 'Untitled');
            return '' +
                '<tr>' +
                    '<td>' + title + '</td>' +
                    '<td>' + escapeHtml(job.package || job.salary || 'Not specified') + '</td>' +
                    '<td>' + escapeHtml(job.deadline || '-') + '</td>' +
                    '<td>' + escapeHtml(formatLabel(job.opportunity_type || '')) + '</td>' +
                    '<td>' + escapeHtml(formatLabel(job.job_type || '')) + '</td>' +
                    '<td>' + escapeHtml(job.application_count || 0) + '</td>' +
                    '<td><button type="button" class="btn small" data-job-id="' + escapeHtml(job.id) + '" data-job-title="' + title + '">View applicants</button></td>' +
                '</tr>';
        }).join('');
    }

    function loadDashboard() {
        fetchJson('company_dashboard.php', {
            method: 'GET',
            headers: { 'Accept': 'application/json' }
        }).then(function (data) {
            showApp();
            renderProfile(data.company || {});
            statJobs.textContent = String((data.stats && data.stats.jobs_posted) || 0);
            statApps.textContent = String((data.stats && data.stats.applications_received) || 0);
            renderJobs(data.jobs || []);
        }).catch(function (error) {
            showAuthGate();
            if (authGate) {
                authGate.innerHTML =
                    '<p>' + escapeHtml(error.message || 'Could not load company dashboard.') + '</p>' +
                    '<p><a href="login.html">Go to login</a></p>';
            }
        });
    }

    function submitJob() {
        if (!postForm) {
            return;
        }

        var formData = new FormData(postForm);
        var payload = {
            title: String(formData.get('title') || '').trim(),
            description: String(formData.get('description') || '').trim(),
            package: String(formData.get('package') || '').trim(),
            deadline: String(formData.get('deadline') || '').trim(),
            opportunity_type: String(formData.get('opportunity_type') || '').trim(),
            job_type: String(formData.get('job_type') || '').trim(),
            location: String(formData.get('location') || '').trim()
        };

        postSubmit.disabled = true;
        postSubmit.textContent = 'Publishing...';
        showPostMessage('', '');

        fetchJson('company_dashboard.php', {
            method: 'POST',
            headers: {
                'Content-Type': 'application/json',
                'Accept': 'application/json'
            },
            body: JSON.stringify(payload)
        }).then(function (data) {
            showPostMessage('ok', data.message || 'Job posted successfully.');
            postForm.reset();
            loadDashboard();
        }).catch(function (error) {
            showPostMessage('err', error.message || 'Could not post job.');
        }).finally(function () {
            postSubmit.disabled = false;
            postSubmit.textContent = 'Publish job';
        });
    }

    function loadApplicants(jobId, jobTitle) {
        modalTitle.textContent = 'Applicants for ' + jobTitle;
        modalBody.innerHTML = '<p>Loading applicants...</p>';
        modal.classList.add('open');
        modal.setAttribute('aria-hidden', 'false');

        fetchJson('company_job_applicants.php?job_id=' + encodeURIComponent(jobId), {
            method: 'GET',
            headers: { 'Accept': 'application/json' }
        }).then(function (data) {
            renderApplicants(data.applicants || []);
        }).catch(function (error) {
            modalBody.innerHTML = '<p class="msg err">' + escapeHtml(error.message || 'Could not load applicants.') + '</p>';
        });
    }

    function renderApplicants(applicants) {
        if (!applicants.length) {
            modalBody.innerHTML = '<p>No applicants yet for this job.</p>';
            return;
        }

        modalBody.innerHTML = applicants.map(function (applicant) {
            var resumeAction = applicant.resume_download_url
                ? '<a class="btn small" href="' + encodeURI(applicant.resume_download_url) + '" target="_blank" rel="noopener noreferrer">Download resume</a>'
                : '<span class="btn small secondary" style="opacity:.75;cursor:default;">No resume</span>';

            return '' +
                '<div class="applicant-card">' +
                    '<h4>' + escapeHtml(applicant.name || 'Student') + '</h4>' +
                    '<div class="applicant-meta">' +
                        '<div><strong>Email:</strong> ' + escapeHtml(applicant.email || '-') + '</div>' +
                        '<div><strong>Phone:</strong> ' + escapeHtml(applicant.phone || '-') + '</div>' +
                        '<div><strong>Roll Number:</strong> ' + escapeHtml(applicant.roll_number || '-') + '</div>' +
                        '<div><strong>Department:</strong> ' + escapeHtml(applicant.department || applicant.course || '-') + '</div>' +
                        '<div><strong>Year of Study:</strong> ' + escapeHtml(applicant.year_of_study || '-') + '</div>' +
                        '<div><strong>Status:</strong> ' + escapeHtml(applicant.status || 'submitted') + '</div>' +
                        '<div><strong>Applied At:</strong> ' + escapeHtml(applicant.applied_at || '-') + '</div>' +
                    '</div>' +
                    '<div class="applicant-actions">' + resumeAction + '</div>' +
                '</div>';
        }).join('');
    }

    function closeModal() {
        modal.classList.remove('open');
        modal.setAttribute('aria-hidden', 'true');
        modalBody.innerHTML = '';
    }
});
