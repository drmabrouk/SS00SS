(function($) {
    'use strict';

    window.VS = {
        config: window.vsConfig || {},

        // Verification Shield SVG Icon
        shieldSvg: `<svg viewBox="0 0 24 24"><path d="M12 22s8-4 8-10V5l-8-3-8 3v7c0 6 8 10 8 10z"/><path d="m9 12 2 2 4-4"/></svg>`,
        clockInSvg: `<svg viewBox="0 0 24 24"><circle cx="12" cy="12" r="10"/><polyline points="12 6 12 12 16 14"/></svg>`,
        clockOutSvg: `<svg viewBox="0 0 24 24"><circle cx="12" cy="12" r="10"/><path d="m15 9-6 6"/><path d="m9 9 6 6"/></svg>`,

        init: function() {
            this.bindEvents();
            this.checkSessionAndRender();
        },

        bindEvents: function() {
            $(document).on('submit', '#vs-login-form', this.handleLogin.bind(this));
            $(document).on('click', '#vs-btn-forgot-password', this.showForgotPasswordModal.bind(this));
            $(document).on('submit', '#vs-forgot-form', this.handleForgotPassword.bind(this));
            $(document).on('submit', '#vs-verify-code-form', this.handleVerifyCode.bind(this));
            $(document).on('submit', '#vs-reset-password-form', this.handleResetPassword.bind(this));
            $(document).on('click', '#vs-btn-clock-in', this.handleClockIn.bind(this));
            $(document).on('click', '#vs-btn-clock-out', this.handleClockOut.bind(this));
            $(document).on('click', '#vs-btn-retry-gps', this.handleClockIn.bind(this));
            $(document).on('click', '#vs-btn-logout', this.handleLogout.bind(this));
            $(document).on('click', '#vs-btn-passkey-login', this.handlePasskeyLogin.bind(this));
            $(document).on('click', '#vs-btn-passkey-register', this.handlePasskeyRegister.bind(this));
            $(document).on('click', '.vs-nav-item', this.handleNavClick.bind(this));
        },

        checkSessionAndRender: function() {
            if (!this.config.is_logged_in) {
                this.renderLogin();
            } else {
                this.renderDashboard(this.config.user_role);
            }
        },

        renderLogin: function() {
            var html = `
                <div class="vs-auth-wrapper">
                    <div class="vs-auth-card">
                        <div class="vs-auth-header">
                            <div style="display:flex; justify-content:center; margin-bottom:8px;">
                                <div style="width:36px; height:36px;">${this.shieldSvg}</div>
                            </div>
                            <h2 class="vs-auth-title">Verification System</h2>
                            <p class="vs-auth-subtitle">Workforce Management & Attendance</p>
                        </div>
                        <form id="vs-login-form">
                            <div class="vs-form-group">
                                <label class="vs-form-label">Phone Number</label>
                                <input type="text" id="vs-phone" class="vs-input" placeholder="+971 50 000 0000" value="+971" required>
                            </div>
                            <div class="vs-form-group">
                                <label class="vs-form-label">Password</label>
                                <input type="password" id="vs-password" class="vs-input" placeholder="••••••••" required>
                            </div>
                            <div class="vs-form-group" style="display: flex; justify-content: space-between; align-items: center;">
                                <label style="font-size:0.85rem;"><input type="checkbox" id="vs-remember" checked> Remember Me</label>
                                <button type="button" class="vs-btn-link" id="vs-btn-forgot-password" style="background:none; border:none; color:var(--vs-text-muted); font-size:0.825rem; cursor:pointer;">Forgot Password?</button>
                            </div>
                            <div id="vs-login-error" style="color:#ef4444; font-size:0.85rem; margin-bottom:12px; display:none;"></div>
                            <button type="submit" class="vs-btn vs-btn-primary" style="width:100%;">Sign In</button>
                        </form>
                        <div style="margin-top: 12px; text-align: center;">
                            <button type="button" id="vs-btn-passkey-login" class="vs-btn vs-btn-outline" style="width:100%;">Sign in with Passkey / Biometrics</button>
                        </div>
                    </div>
                </div>
            `;
            $('#vs-app-root').html(html);
        },

        handleLogin: function(e) {
            e.preventDefault();
            var phone = $('#vs-phone').val();
            var password = $('#vs-password').val();
            var remember = $('#vs-remember').is(':checked');

            $('#vs-login-error').hide().text('');

            $.post(this.config.ajax_url, {
                action: 'vs_login',
                nonce: this.config.nonce,
                phone: phone,
                password: password,
                remember: remember
            }, function(res) {
                if (res.success) {
                    window.location.reload();
                } else {
                    $('#vs-login-error').text(res.data.message).show();
                }
            });
        },

        showForgotPasswordModal: function() {
            var html = `
                <div class="vs-modal-backdrop" id="vs-forgot-modal">
                    <div class="vs-modal">
                        <h3 class="vs-auth-title">Password Recovery</h3>
                        <p class="vs-auth-subtitle">Enter registered phone number to receive a 6-digit code.</p>
                        <form id="vs-forgot-form" style="margin-top:16px;">
                            <div class="vs-form-group">
                                <input type="text" id="vs-recovery-phone" class="vs-input" value="+971" required>
                            </div>
                            <div id="vs-forgot-msg" style="margin-bottom:12px; font-size:0.85rem;"></div>
                            <button type="submit" class="vs-btn vs-btn-primary" style="width:100%;">Send Verification Code</button>
                            <button type="button" onclick="$('#vs-forgot-modal').remove()" class="vs-btn vs-btn-outline" style="width:100%; margin-top:8px;">Cancel</button>
                        </form>
                    </div>
                </div>
            `;
            $('body').append(html);
        },

        handleForgotPassword: function(e) {
            e.preventDefault();
            var phone = $('#vs-recovery-phone').val();

            $.post(this.config.ajax_url, {
                action: 'vs_forgot_password',
                nonce: this.config.nonce,
                phone: phone
            }, function(res) {
                if (res.success) {
                    VS.showCodeVerificationForm(phone);
                } else {
                    $('#vs-forgot-msg').text(res.data.message).css('color', '#ef4444');
                }
            });
        },

        showCodeVerificationForm: function(phone) {
            var html = `
                <div class="vs-modal-backdrop" id="vs-forgot-modal">
                    <div class="vs-modal">
                        <h3 class="vs-auth-title">Enter Verification Code</h3>
                        <p class="vs-auth-subtitle">Enter the 6-digit code sent to your email.</p>
                        <form id="vs-verify-code-form" style="margin-top:16px;">
                            <input type="hidden" id="vs-verify-phone" value="${phone}">
                            <div class="vs-form-group">
                                <input type="text" id="vs-code-input" class="vs-input" maxlength="6" placeholder="123456" style="letter-spacing:6px; text-align:center; font-size:1.25rem;" required>
                            </div>
                            <div id="vs-verify-msg" style="margin-bottom:12px; font-size:0.85rem;"></div>
                            <button type="submit" class="vs-btn vs-btn-primary" style="width:100%;">Verify Code</button>
                        </form>
                    </div>
                </div>
            `;
            $('#vs-forgot-modal').remove();
            $('body').append(html);
        },

        handleVerifyCode: function(e) {
            e.preventDefault();
            var phone = $('#vs-verify-phone').val();
            var code = $('#vs-code-input').val();

            $.post(this.config.ajax_url, {
                action: 'vs_verify_recovery_code',
                nonce: this.config.nonce,
                phone: phone,
                code: code
            }, function(res) {
                if (res.success) {
                    VS.showResetPasswordForm(res.data.reset_token);
                } else {
                    $('#vs-verify-msg').text(res.data.message).css('color', '#ef4444');
                }
            });
        },

        showResetPasswordForm: function(resetToken) {
            var html = `
                <div class="vs-modal-backdrop" id="vs-forgot-modal">
                    <div class="vs-modal">
                        <h3 class="vs-auth-title">Reset Password</h3>
                        <form id="vs-reset-password-form" style="margin-top:16px;">
                            <input type="hidden" id="vs-reset-token" value="${resetToken}">
                            <div class="vs-form-group">
                                <label class="vs-form-label">New Password</label>
                                <input type="password" id="vs-new-password" class="vs-input" minlength="8" required>
                            </div>
                            <div class="vs-form-group">
                                <label class="vs-form-label">Confirm Password</label>
                                <input type="password" id="vs-confirm-password" class="vs-input" minlength="8" required>
                            </div>
                            <div id="vs-reset-msg" style="margin-bottom:12px; font-size:0.85rem;"></div>
                            <button type="submit" class="vs-btn vs-btn-primary" style="width:100%;">Reset Password</button>
                        </form>
                    </div>
                </div>
            `;
            $('#vs-forgot-modal').remove();
            $('body').append(html);
        },

        handleResetPassword: function(e) {
            e.preventDefault();
            var token = $('#vs-reset-token').val();
            var newPass = $('#vs-new-password').val();
            var confirmPass = $('#vs-confirm-password').val();

            $.post(this.config.ajax_url, {
                action: 'vs_reset_password',
                nonce: this.config.nonce,
                reset_token: token,
                new_password: newPass,
                confirm_password: confirmPass
            }, function(res) {
                if (res.success) {
                    alert(res.data.message);
                    $('#vs-forgot-modal').remove();
                    VS.renderLogin();
                } else {
                    $('#vs-reset-msg').text(res.data.message).css('color', '#ef4444');
                }
            });
        },

        renderDashboard: function(role) {
            var navItems = '';

            if (role === 'vs_employee') {
                navItems = `
                    <a class="vs-nav-item active" data-view="employee-home">Dashboard</a>
                    <a class="vs-nav-item" data-view="employee-history">Attendance History</a>
                    <a class="vs-nav-item" data-view="employee-payroll">Payroll Statement</a>
                    <a class="vs-nav-item" data-view="employee-leave">Leave Requests</a>
                `;
            } else {
                navItems = `
                    <a class="vs-nav-item active" data-view="manager-home">Dashboard</a>
                    <a class="vs-nav-item" data-view="manager-employees">Employees</a>
                    <a class="vs-nav-item" data-view="manager-branches">Branches</a>
                    <a class="vs-nav-item" data-view="manager-attendance">Attendance Records</a>
                    <a class="vs-nav-item" data-view="manager-payroll">Payroll Engine</a>
                `;
            }

            var layout = `
                <div class="vs-sidebar">
                    <div class="vs-sidebar-brand">
                        <div style="width:22px; height:22px;">${this.shieldSvg}</div>
                        <span>Verification System</span>
                    </div>
                    <nav class="vs-sidebar-nav">
                        ${navItems}
                    </nav>
                    <div class="vs-sidebar-footer">
                        <button id="vs-btn-passkey-register" class="vs-btn vs-btn-outline" style="width:100%; margin-bottom:4px; font-size:0.8rem;">Register Passkey</button>
                        <button id="vs-btn-logout" class="vs-btn vs-btn-outline" style="width:100%; font-size:0.8rem;">Sign Out</button>
                    </div>
                </div>
                <div class="vs-main-content">
                    <header class="vs-header">
                        <h1 class="vs-header-title" id="vs-view-title">Overview</h1>
                        <div class="vs-user-menu">
                            <span>Role: <strong>${role.replace('vs_', '').toUpperCase()}</strong></span>
                        </div>
                    </header>
                    <div id="vs-dashboard-body">
                        <!-- Dynamic View Content -->
                    </div>
                </div>
            `;

            $('#vs-app-root').html(layout);
            this.loadView(role === 'vs_employee' ? 'employee-home' : 'manager-home');
        },

        handleNavClick: function(e) {
            e.preventDefault();
            var target = $(e.currentTarget);
            $('.vs-nav-item').removeClass('active');
            target.addClass('active');

            var view = target.data('view');
            this.loadView(view);
        },

        loadView: function(view) {
            $('#vs-view-title').text(view.replace('-', ' ').toUpperCase());

            if (view === 'employee-home') {
                this.renderEmployeeHome();
            } else if (view === 'manager-home') {
                this.renderManagerHome();
            } else if (view === 'manager-employees') {
                this.renderEmployeesList();
            } else if (view === 'manager-branches') {
                this.renderBranchesList();
            } else if (view === 'manager-payroll') {
                this.renderManagerPayroll();
            } else if (view === 'employee-payroll') {
                this.renderEmployeePayroll();
            } else if (view === 'employee-history' || view === 'manager-attendance') {
                this.renderAttendanceHistory();
            } else if (view === 'employee-leave') {
                this.renderLeaveRequests();
            }
        },

        renderEmployeeHome: function() {
            var html = `
                <div class="vs-grid">
                    <div class="vs-card" style="text-align:center;">
                        <div class="vs-card-title">GPS Attendance Verification</div>
                        <div style="margin:16px 0; display:flex; justify-content:center; gap:8px;">
                            <button id="vs-btn-clock-in" class="vs-btn vs-btn-primary vs-btn-lg">
                                ${this.clockInSvg} Clock In
                            </button>
                            <button id="vs-btn-clock-out" class="vs-btn vs-btn-outline vs-btn-lg">
                                ${this.clockOutSvg} Clock Out
                            </button>
                        </div>
                        <div id="vs-gps-status-box" class="vs-gps-status" style="display:none;"></div>
                    </div>
                    <div class="vs-card">
                        <div class="vs-card-title">Attendance Compliance</div>
                        <div class="vs-card-value" id="vs-compliance-pct">100%</div>
                    </div>
                </div>
            `;
            $('#vs-dashboard-body').html(html);
        },

        renderManagerHome: function() {
            $.get(this.config.ajax_url, { action: 'vs_get_dashboard_stats', nonce: this.config.nonce }, function(res) {
                if (res.success) {
                    var s = res.data.stats;
                    var html = `
                        <div class="vs-grid">
                            <div class="vs-card"><div class="vs-card-title">Total Active Employees</div><div class="vs-card-value">${s.total_employees}</div></div>
                            <div class="vs-card"><div class="vs-card-title">Present Today</div><div class="vs-card-value">${s.present_today}</div></div>
                            <div class="vs-card"><div class="vs-card-title">Late Today</div><div class="vs-card-value">${s.late_today}</div></div>
                            <div class="vs-card"><div class="vs-card-title">Pending Leave Requests</div><div class="vs-card-value">${s.pending_leave}</div></div>
                        </div>
                    `;
                    $('#vs-dashboard-body').html(html);
                }
            });
        },

        renderEmployeesList: function() {
            var self = this;
            $.get(this.config.ajax_url, { action: 'vs_get_users', nonce: this.config.nonce }, function(res) {
                if (res.success) {
                    var rows = res.data.users.map(function(u) {
                        return `
                            <tr>
                                <td>${u.employee_code}</td>
                                <td>${u.display_name}</td>
                                <td>${u.phone}</td>
                                <td>${u.branch_name || 'N/A'}</td>
                                <td>${u.job_title || 'Employee'}</td>
                                <td><span class="vs-badge vs-badge-success">${u.status}</span></td>
                            </tr>
                        `;
                    }).join('');

                    var html = `
                        <div class="vs-card" style="margin-bottom:16px;">
                            <h3 style="font-size:1rem; font-weight:700;">Create New Employee</h3>
                            <form id="vs-create-user-form" style="display:grid; grid-template-columns: repeat(auto-fit, minmax(180px, 1fr)); gap:10px; margin-top:10px;">
                                <div><label class="vs-form-label">Full Name</label><input type="text" id="vs-new-name" class="vs-input" required></div>
                                <div><label class="vs-form-label">Phone (+971...)</label><input type="text" id="vs-new-phone" class="vs-input" value="+971" required></div>
                                <div><label class="vs-form-label">Password</label><input type="password" id="vs-new-pass" class="vs-input" required></div>
                                <div><label class="vs-form-label">Job Title</label><input type="text" id="vs-new-title" class="vs-input" value="Employee"></div>
                                <div><label class="vs-form-label">Basic Salary (AED)</label><input type="number" id="vs-new-salary" class="vs-input" value="5000"></div>
                                <div style="grid-column: 1 / -1;"><button type="submit" class="vs-btn vs-btn-primary">Add Employee Account</button></div>
                            </form>
                        </div>
                        <div class="vs-table-wrapper">
                            <table class="vs-table">
                                <thead><tr><th>Code</th><th>Name</th><th>Phone</th><th>Branch</th><th>Job Title</th><th>Status</th></tr></thead>
                                <tbody>${rows || '<tr><td colspan="6">No employees found.</td></tr>'}</tbody>
                            </table>
                        </div>
                    `;
                    $('#vs-dashboard-body').html(html);

                    $(document).off('submit', '#vs-create-user-form').on('submit', '#vs-create-user-form', function(e) {
                        e.preventDefault();
                        $.post(VS.config.ajax_url, {
                            action: 'vs_create_user',
                            nonce: VS.config.nonce,
                            full_name: $('#vs-new-name').val(),
                            phone: $('#vs-new-phone').val(),
                            password: $('#vs-new-pass').val(),
                            job_title: $('#vs-new-title').val(),
                            basic_salary: $('#vs-new-salary').val()
                        }, function(cRes) {
                            alert(cRes.data.message);
                            if (cRes.success) { self.renderEmployeesList(); }
                        });
                    });
                }
            });
        },

        renderBranchesList: function() {
            var self = this;
            $.get(this.config.ajax_url, { action: 'vs_get_branches', nonce: this.config.nonce }, function(res) {
                if (res.success) {
                    var rows = res.data.branches.map(function(b) {
                        return `
                            <tr>
                                <td>${b.branch_code}</td>
                                <td>${b.name}</td>
                                <td>${b.address || 'N/A'}</td>
                                <td>${b.latitude}, ${b.longitude}</td>
                                <td>${b.geofence_radius}m</td>
                                <td><span class="vs-badge vs-badge-success">${b.status}</span></td>
                            </tr>
                        `;
                    }).join('');

                    var html = `
                        <div class="vs-card" style="margin-bottom:16px;">
                            <h3 style="font-size:1rem; font-weight:700;">Create New Branch</h3>
                            <form id="vs-create-branch-form" style="display:grid; grid-template-columns: repeat(auto-fit, minmax(160px, 1fr)); gap:10px; margin-top:10px;">
                                <div><label class="vs-form-label">Branch Name</label><input type="text" id="vs-b-name" class="vs-input" required></div>
                                <div><label class="vs-form-label">Address</label><input type="text" id="vs-b-addr" class="vs-input"></div>
                                <div><label class="vs-form-label">Latitude</label><input type="text" id="vs-b-lat" class="vs-input" value="25.2048" required></div>
                                <div><label class="vs-form-label">Longitude</label><input type="text" id="vs-b-lng" class="vs-input" value="55.2708" required></div>
                                <div><label class="vs-form-label">Geofence Radius (m)</label><input type="number" id="vs-b-rad" class="vs-input" value="100" required></div>
                                <div style="grid-column: 1 / -1;"><button type="submit" class="vs-btn vs-btn-primary">Add Branch Location</button></div>
                            </form>
                        </div>
                        <div class="vs-table-wrapper">
                            <table class="vs-table">
                                <thead><tr><th>Code</th><th>Name</th><th>Address</th><th>GPS Coordinates</th><th>Geofence</th><th>Status</th></tr></thead>
                                <tbody>${rows || '<tr><td colspan="6">No branches created yet.</td></tr>'}</tbody>
                            </table>
                        </div>
                    `;
                    $('#vs-dashboard-body').html(html);

                    $(document).off('submit', '#vs-create-branch-form').on('submit', '#vs-create-branch-form', function(e) {
                        e.preventDefault();
                        $.post(VS.config.ajax_url, {
                            action: 'vs_create_branch',
                            nonce: VS.config.nonce,
                            name: $('#vs-b-name').val(),
                            address: $('#vs-b-addr').val(),
                            latitude: $('#vs-b-lat').val(),
                            longitude: $('#vs-b-lng').val(),
                            geofence_radius: $('#vs-b-rad').val()
                        }, function(bRes) {
                            alert(bRes.data.message);
                            if (bRes.success) { self.renderBranchesList(); }
                        });
                    });
                }
            });
        },

        renderManagerPayroll: function() {
            $.get(this.config.ajax_url, { action: 'vs_get_payroll_statement', nonce: this.config.nonce }, function(res) {
                var records = res.success ? res.data.records : [];
                var rows = records.map(function(r) {
                    return `
                        <tr>
                            <td>${r.period_name || 'Period #' + r.period_id}</td>
                            <td>${r.display_name || 'Employee'}</td>
                            <td>${r.basic_salary} AED</td>
                            <td>${r.allowances} AED</td>
                            <td>${r.overtime_pay} AED</td>
                            <td>${r.total_deductions} AED</td>
                            <td><strong>${r.net_salary} AED</strong></td>
                            <td><span class="vs-badge vs-badge-success">${r.status}</span></td>
                        </tr>
                    `;
                }).join('');

                var html = `
                    <div class="vs-card" style="margin-bottom:16px;">
                        <h3 style="font-size:1rem; font-weight:700; margin-bottom:10px;">Calculate Payroll Period</h3>
                        <form id="vs-run-payroll-form" style="display:flex; gap:10px; align-items:flex-end;">
                            <div>
                                <label class="vs-form-label">Period Name</label>
                                <input type="text" id="vs-pay-name" class="vs-input" value="Payroll - ${new Date().toLocaleString('default', { month: 'long', year: 'numeric' })}" required>
                            </div>
                            <div>
                                <label class="vs-form-label">Start Date</label>
                                <input type="date" id="vs-pay-start" class="vs-input" required>
                            </div>
                            <div>
                                <label class="vs-form-label">End Date</label>
                                <input type="date" id="vs-pay-end" class="vs-input" required>
                            </div>
                            <button type="submit" class="vs-btn vs-btn-primary">Calculate Payroll</button>
                        </form>
                    </div>
                    <div class="vs-table-wrapper">
                        <table class="vs-table">
                            <thead><tr><th>Period</th><th>Employee</th><th>Basic</th><th>Allowances</th><th>Overtime</th><th>Deductions</th><th>Net Pay</th><th>Status</th></tr></thead>
                            <tbody>${rows || '<tr><td colspan="8">No payroll records calculated yet.</td></tr>'}</tbody>
                        </table>
                    </div>
                `;
                $('#vs-dashboard-body').html(html);

                $(document).off('submit', '#vs-run-payroll-form').on('submit', '#vs-run-payroll-form', function(e) {
                    e.preventDefault();
                    $.post(VS.config.ajax_url, {
                        action: 'vs_calculate_payroll_period',
                        nonce: VS.config.nonce,
                        period_name: $('#vs-pay-name').val(),
                        start_date: $('#vs-pay-start').val(),
                        end_date: $('#vs-pay-end').val()
                    }, function(res) {
                        alert(res.data.message);
                        if (res.success) { VS.renderManagerPayroll(); }
                    });
                });
            });
        },

        renderEmployeePayroll: function() {
            $.get(this.config.ajax_url, { action: 'vs_get_payroll_statement', nonce: this.config.nonce }, function(res) {
                var records = res.success ? res.data.records : [];
                var rows = records.map(function(r) {
                    return `
                        <tr>
                            <td>${r.period_name}</td>
                            <td>${r.basic_salary} AED</td>
                            <td>${r.allowances} AED</td>
                            <td>${r.overtime_pay} AED</td>
                            <td>${r.total_deductions} AED</td>
                            <td><strong>${r.net_salary} AED</strong></td>
                            <td><span class="vs-badge vs-badge-success">${r.status}</span></td>
                        </tr>
                    `;
                }).join('');

                var html = `
                    <div class="vs-table-wrapper">
                        <table class="vs-table">
                            <thead><tr><th>Payroll Period</th><th>Basic Salary</th><th>Allowances</th><th>Overtime</th><th>Deductions</th><th>Net Pay</th><th>Status</th></tr></thead>
                            <tbody>${rows || '<tr><td colspan="7">No payroll statements available.</td></tr>'}</tbody>
                        </table>
                    </div>
                `;
                $('#vs-dashboard-body').html(html);
            });
        },

        renderAttendanceHistory: function() {
            $.get(this.config.ajax_url, { action: 'vs_get_attendance_report', nonce: this.config.nonce }, function(res) {
                if (res.success) {
                    var rows = res.data.attendance.map(function(item) {
                        return `
                            <tr>
                                <td>${item.attendance_date}</td>
                                <td>${item.display_name || 'Self'}</td>
                                <td>${item.clock_in ? item.clock_in.split(' ')[1] : '--'}</td>
                                <td>${item.clock_out ? item.clock_out.split(' ')[1] : '--'}</td>
                                <td><span class="vs-badge vs-badge-success">${item.status}</span></td>
                            </tr>
                        `;
                    }).join('');

                    var html = `
                        <div class="vs-table-wrapper">
                            <table class="vs-table">
                                <thead>
                                    <tr>
                                        <th>Date</th>
                                        <th>Employee</th>
                                        <th>Clock In</th>
                                        <th>Clock Out</th>
                                        <th>Status</th>
                                    </tr>
                                </thead>
                                <tbody>${rows || '<tr><td colspan="5">No attendance records found.</td></tr>'}</tbody>
                            </table>
                        </div>
                    `;
                    $('#vs-dashboard-body').html(html);
                }
            });
        },

        renderLeaveRequests: function() {
            var self = this;
            $.get(this.config.ajax_url, { action: 'vs_get_leave_requests', nonce: this.config.nonce }, function(res) {
                var requests = res.success ? res.data.requests : [];
                var rows = requests.map(function(r) {
                    return `
                        <tr>
                            <td>${r.leave_type}</td>
                            <td>${r.start_date} to ${r.end_date}</td>
                            <td>${r.reason || 'N/A'}</td>
                            <td><span class="vs-badge vs-badge-success">${r.status}</span></td>
                        </tr>
                    `;
                }).join('');

                var html = `
                    <div class="vs-card" style="margin-bottom:16px;">
                        <h3 style="font-size:1rem; font-weight:700;">Submit New Leave Request</h3>
                        <form id="vs-submit-leave-form" style="display:grid; grid-template-columns: repeat(auto-fit, minmax(180px, 1fr)); gap:10px; margin-top:10px;">
                            <div>
                                <label class="vs-form-label">Leave Type</label>
                                <select id="vs-leave-type" class="vs-select">
                                    <option value="annual">Annual Leave</option>
                                    <option value="sick">Sick Leave</option>
                                    <option value="unpaid">Unpaid Leave</option>
                                </select>
                            </div>
                            <div><label class="vs-form-label">Start Date</label><input type="date" id="vs-leave-start" class="vs-input" required></div>
                            <div><label class="vs-form-label">End Date</label><input type="date" id="vs-leave-end" class="vs-input" required></div>
                            <div style="grid-column: 1 / -1;"><label class="vs-form-label">Reason</label><input type="text" id="vs-leave-reason" class="vs-input" required></div>
                            <div style="grid-column: 1 / -1;"><button type="submit" class="vs-btn vs-btn-primary">Submit Request</button></div>
                        </form>
                    </div>
                    <div class="vs-table-wrapper">
                        <table class="vs-table">
                            <thead><tr><th>Type</th><th>Period</th><th>Reason</th><th>Status</th></tr></thead>
                            <tbody>${rows || '<tr><td colspan="4">No leave requests submitted.</td></tr>'}</tbody>
                        </table>
                    </div>
                `;
                $('#vs-dashboard-body').html(html);

                $(document).off('submit', '#vs-submit-leave-form').on('submit', '#vs-submit-leave-form', function(e) {
                    e.preventDefault();
                    $.post(VS.config.ajax_url, {
                        action: 'vs_submit_leave_request',
                        nonce: VS.config.nonce,
                        leave_type: $('#vs-leave-type').val(),
                        start_date: $('#vs-leave-start').val(),
                        end_date: $('#vs-leave-end').val(),
                        reason: $('#vs-leave-reason').val()
                    }, function(lRes) {
                        alert(lRes.data.message);
                        if (lRes.success) { self.renderLeaveRequests(); }
                    });
                });
            });
        },

        // Refined GPS Attendance Experience
        handleClockIn: function() {
            var $box = $('#vs-gps-status-box').removeClass('error success').text('Detecting current location...').show();
            $('#vs-btn-clock-in').prop('disabled', true);

            if (!navigator.geolocation) {
                $box.addClass('error').text('Geolocation service is unavailable in your browser.');
                $('#vs-btn-clock-in').prop('disabled', false);
                return;
            }

            navigator.geolocation.getCurrentPosition(function(pos) {
                $box.text('Verifying branch location...');
                $.post(VS.config.ajax_url, {
                    action: 'vs_clock_in',
                    nonce: VS.config.nonce,
                    latitude: pos.coords.latitude,
                    longitude: pos.coords.longitude,
                    accuracy: pos.coords.accuracy
                }, function(res) {
                    $('#vs-btn-clock-in').prop('disabled', false);
                    if (res.success) {
                        $box.addClass('success').text(res.data.message);
                    } else {
                        $box.addClass('error').html(res.data.message + ' <button id="vs-btn-retry-gps" class="vs-btn vs-btn-outline" style="height:26px; padding:0 8px; font-size:0.75rem; margin-left:8px;">Retry Location</button>');
                    }
                });
            }, function(err) {
                $('#vs-btn-clock-in').prop('disabled', false);
                $box.addClass('error').html('Location error: ' + err.message + ' <button id="vs-btn-retry-gps" class="vs-btn vs-btn-outline" style="height:26px; padding:0 8px; font-size:0.75rem; margin-left:8px;">Retry Location</button>');
            }, { enableHighAccuracy: true, timeout: 10000 });
        },

        handleClockOut: function() {
            var $box = $('#vs-gps-status-box').removeClass('error success').text('Detecting current location...').show();
            $('#vs-btn-clock-out').prop('disabled', true);

            if (!navigator.geolocation) {
                $box.addClass('error').text('Geolocation service is unavailable in your browser.');
                $('#vs-btn-clock-out').prop('disabled', false);
                return;
            }

            navigator.geolocation.getCurrentPosition(function(pos) {
                $box.text('Verifying branch location...');
                $.post(VS.config.ajax_url, {
                    action: 'vs_clock_out',
                    nonce: VS.config.nonce,
                    latitude: pos.coords.latitude,
                    longitude: pos.coords.longitude,
                    accuracy: pos.coords.accuracy
                }, function(res) {
                    $('#vs-btn-clock-out').prop('disabled', false);
                    if (res.success) {
                        $box.addClass('success').text(res.data.message);
                    } else {
                        $box.addClass('error').text(res.data.message);
                    }
                });
            }, function(err) {
                $('#vs-btn-clock-out').prop('disabled', false);
                $box.addClass('error').text('Location error: ' + err.message);
            }, { enableHighAccuracy: true, timeout: 10000 });
        },

        handleLogout: function() {
            $.post(this.config.ajax_url, { action: 'vs_logout', nonce: this.config.nonce }, function() {
                window.location.reload();
            });
        },

        handlePasskeyRegister: function() {
            if (!window.PublicKeyCredential) {
                alert('WebAuthn / Passkeys are not supported on this device/browser.');
                return;
            }

            $.post(this.config.ajax_url, { action: 'vs_passkey_register_options', nonce: this.config.nonce }, function(res) {
                if (res.success) {
                    var options = res.data;
                    options.challenge = Uint8Array.from(atob(options.challenge), function(c) { return c.charCodeAt(0); });
                    options.user.id = Uint8Array.from(atob(options.user.id), function(c) { return c.charCodeAt(0); });

                    navigator.credentials.create({ publicKey: options })
                        .then(function(credential) {
                            var clientDataJSON = btoa(String.fromCharCode.apply(null, new Uint8Array(credential.response.clientDataJSON)));
                            var rawCred = {
                                id: credential.id,
                                rawId: btoa(String.fromCharCode.apply(null, new Uint8Array(credential.rawId))),
                                type: credential.type,
                                clientDataJSON: clientDataJSON
                            };

                            $.post(VS.config.ajax_url, {
                                action: 'vs_passkey_register_verify',
                                nonce: VS.config.nonce,
                                credential: JSON.stringify(rawCred)
                            }, function(vRes) {
                                alert(vRes.data.message);
                            });
                        })
                        .catch(function(err) {
                            alert('Passkey creation failed: ' + err.message);
                        });
                }
            });
        },

        handlePasskeyLogin: function() {
            if (!window.PublicKeyCredential) {
                alert('WebAuthn / Passkeys are not supported on this device/browser.');
                return;
            }

            var phone = $('#vs-phone').val();

            $.post(this.config.ajax_url, { action: 'vs_passkey_auth_options', nonce: this.config.nonce, phone: phone }, function(res) {
                if (res.success) {
                    var options = res.data;
                    options.challenge = Uint8Array.from(atob(options.challenge), function(c) { return c.charCodeAt(0); });

                    if (options.allowCredentials) {
                        options.allowCredentials.forEach(function(c) {
                            c.id = Uint8Array.from(atob(c.id), function(ch) { return ch.charCodeAt(0); });
                        });
                    }

                    navigator.credentials.get({ publicKey: options })
                        .then(function(assertion) {
                            var clientDataJSON = btoa(String.fromCharCode.apply(null, new Uint8Array(assertion.response.clientDataJSON)));
                            var rawCred = {
                                id: assertion.id,
                                rawId: btoa(String.fromCharCode.apply(null, new Uint8Array(assertion.rawId))),
                                type: assertion.type,
                                clientDataJSON: clientDataJSON
                            };

                            $.post(VS.config.ajax_url, {
                                action: 'vs_passkey_auth_verify',
                                nonce: VS.config.nonce,
                                session_challenge_id: options.session_challenge_id,
                                credential: JSON.stringify(rawCred)
                            }, function(vRes) {
                                if (vRes.success) {
                                    window.location.reload();
                                } else {
                                    alert(vRes.data.message);
                                }
                            });
                        })
                        .catch(function(err) {
                            alert('Passkey authentication failed: ' + err.message);
                        });
                }
            });
        }
    };

    $(document).ready(function() {
        window.VS.init();
    });

})(jQuery);
