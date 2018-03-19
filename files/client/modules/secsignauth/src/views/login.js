/*
 Copyright (C) 2018 by Joachim Meyer
 
 Permission is hereby granted, free of charge, to any person obtaining a copy
 of this software and associated documentation files (the "Software"), to deal
 in the Software without restriction, including without limitation the rights
 to use, copy, modify, merge, publish, distribute, sublicense, and/or sell
 copies of the Software, and to permit persons to whom the Software is
 furnished to do so, subject to the following conditions:
 
 The above copyright notice and this permission notice shall be included in
 all copies or substantial portions of the Software.
 
 THE SOFTWARE IS PROVIDED "AS IS", WITHOUT WARRANTY OF ANY KIND, EXPRESS OR
 IMPLIED, INCLUDING BUT NOT LIMITED TO THE WARRANTIES OF MERCHANTABILITY,
 FITNESS FOR A PARTICULAR PURPOSE AND NONINFRINGEMENT. IN NO EVENT SHALL THE
 AUTHORS OR COPYRIGHT HOLDERS BE LIABLE FOR ANY CLAIM, DAMAGES OR OTHER
 LIABILITY, WHETHER IN AN ACTION OF CONTRACT, TORT OR OTHERWISE, ARISING FROM,
 OUT OF OR IN CONNECTION WITH THE SOFTWARE OR THE USE OR OTHER DEALINGS IN
 THE SOFTWARE.
 */

Espo.define('secsignauth:views/login', 'view', function (Dep) {

    return Dep.extend({

        template: 'secsignauth:login',

        authHeader: '',
        useSecSign: false,
        
        views: {
            footer: {
                el: 'body > footer',
                view: 'views/site/footer'
            },
        },

        events: {
            'submit #login-form': function (e) {
                this.login();
                return false;
            },
            'submit #secSign-form': function (e) {
                this.validateSecSignAndLogin();
                return false;
            },
            'click a[data-action="passwordChangeRequest"]': function (e) {
                this.showPasswordChangeRequest();
            }
        },

        data: function () {
            return {
                logoSrc: this.getLogoSrc()
            };
        },

        init: function () {
            $.ajax({
                url: 'SecSign/use',
                success: function (data) {
                    this.useSecSign = data.useSecSign;
                }.bind(this)
            });
        },
        
        getLogoSrc: function () {
            var companyLogoId = this.getConfig().get('companyLogoId');
            if (!companyLogoId) {
                return this.getBasePath() + (this.getThemeManager().getParam('logo') || 'client/img/logo.png');
            }
            return this.getBasePath() + '?entryPoint=LogoImage&id='+companyLogoId+'&t=' + companyLogoId;
        },

        login: function () {

                var userName = $('#field-userName').val();
                var trimmedUserName = userName.trim();
                if (trimmedUserName !== userName) {
                    $('#field-userName').val(trimmedUserName);
                    userName = trimmedUserName;
                }

                var password = $('#field-password').val();

                var $submit = this.$el.find('#btn-login');

                if (userName == '') {
                    var $el = $("#field-userName");

                    var message = this.getLanguage().translate('userCantBeEmpty', 'messages', 'User');
                    $el.popover({
                        placement: 'bottom',
                        content: message,
                        trigger: 'manual',
                    }).popover('show');

                    var $cell = $el.closest('.form-group');
                    $cell.addClass('has-error');
                    this.$el.one('mousedown click', function () {
                        $cell.removeClass('has-error');
                        $el.popover('destroy');
                    });
                    return;
                }

                $submit.addClass('disabled');

                this.notify('Please wait...');
                
                this.authHeader = Base64.encode(userName + ':' + password);
                if(this.useSecSign){
                    $.ajax({
                        url: 'SecSign/request',
                        headers: {
                            'Authorization': 'Basic ' + this.authHeader,
                            'Espo-Authorization': this.authHeader
                        },
                        success: function (data) {
                            $("#secSignImage").attr('src', 'data:image/png;base64,' + data.img);
                            
                            $("input[name='requestid']").val(data.requestid);
                            $("input[name='secsignid']").val(data.secsignid);
                            $("input[name='authsessionid']").val(data.authsessionid);
                            $("input[name='servicename']").val(data.servicename);
                            $("input[name='serviceaddress']").val(data.serviceaddress);
                            
                            $("#secSignDiv").removeClass('hidden');
                            
                            this.authHeader = Base64.encode(userName + ':' + data.requestid + "__" + password);
                            
                            this.notify(false);
                        }.bind(this)
                    });
                } else {
                    $.ajax({
                    url: 'App/user',
                    headers: {
                        'Authorization': 'Basic ' + this.authHeader,
                        'Espo-Authorization': this.authHeader
                    },
                    success: function (data) {
                        this.notify(false);
                        this.trigger('login', {
                            auth: {
                                userName: userName,
                                token: data.token
                            },
                            user: data.user,
                            preferences: data.preferences,
                            acl: data.acl,
                            settings: data.settings,
                            appParams: data.appParams
                        });
                    }.bind(this),
                    error: function (xhr) {
                        $submit.removeClass('disabled');
                        if (xhr.status == 401) {
                            this.onWrong();
                        }
                    }.bind(this),
                    login: true,
                });
                }
        },
        
        validateSecSignAndLogin: function() {
            this.notify('Please wait...');
            $.ajax({
                    url: 'SecSign/validate',
                    headers: {
                        'Authorization': 'Basic ' + this.authHeader,
                        'Espo-Authorization': this.authHeader
                    },
                    type: 'post',
                    data: JSON.stringify({
                        requestid: $("input[name='requestid']").val(),
                        secsignid: $("input[name='secsignid']").val(),
                        authsessionid: $("input[name='authsessionid']").val(),
                        servicename: $("input[name='servicename']").val(),
                        serviceaddress: $("input[name='serviceaddress']").val()
                    }),
                    success: function (data) {
                        
                        if(data.status == 'authenticated'){
                            $.ajax({
                                url: 'App/user',
                                headers: {
                                    'Authorization': 'Basic ' + this.authHeader,
                                    'Espo-Authorization': this.authHeader
                                },
                                success: function (data) {
                                    this.trigger('login', {
                                        auth: {
                                            userName: data.user.userName,
                                            token: data.token
                                        },
                                        user: data.user,
                                        preferences: data.preferences,
                                        acl: data.acl,
                                        settings: data.settings,
                                        appParams: data.appParams
                                    });

                                    this.notify(false);                        
                                }.bind(this),
                                error: function (xhr) {
                                    $("#secSignDiv").addClass('hidden');
                                    $("#btn-login").removeClass('disabled');
                                    
                                    $("#secSignImage").attr('src', '');
                                
                                    $("input[name='requestid']").val('');
                                    $("input[name='secsignid']").val('');
                                    $("input[name='authsessionid']").val('');
                                    $("input[name='servicename']").val('');
                                    $("input[name='serviceaddress']").val('');
                                    if (xhr.status == 401) {
                                        this.onWrong();
                                    }
                                }.bind(this),
                                login: true,
                            });
                            this.notify(false);
                        } else if(data.status == 'denied'){
                            Espo.Ui.error(this.translate('denied', 'messages', 'SecSign'));
                            
                            $("#secSignDiv").addClass('hidden');
                            $("#btn-login").removeClass('disabled');
                            
                            $("#secSignImage").attr('src', '');
                        
                            $("input[name='requestid']").val('');
                            $("input[name='secsignid']").val('');
                            $("input[name='authsessionid']").val('');
                            $("input[name='servicename']").val('');
                            $("input[name='serviceaddress']").val('');
                        } else if(data.status == 'timeout') {
                            this.onTimeout();
                        } else if(data.status == 'pending') {
                            Espo.Ui.notify(this.translate('pending', 'messages', 'SecSign'));
                        } else {
                            Espo.Ui.error(this.translate('unknown', 'messages', 'SecSign'));
                            
                            $("#secSignDiv").addClass('hidden');
                            $("#btn-login").removeClass('disabled');
                            
                            $("#secSignImage").attr('src', '');
                        
                            $("input[name='requestid']").val('');
                            $("input[name='secsignid']").val('');
                            $("input[name='authsessionid']").val('');
                            $("input[name='servicename']").val('');
                            $("input[name='serviceaddress']").val('');
                        }
                    }.bind(this),
                    error: function (xhr) {
                        if (xhr.status == 401) {
                            this.onTimeout();
                        }
                    }.bind(this)
                    });
        },
        
        onWrong: function () {
            var cell = $('#login .form-group');
            cell.addClass('has-error');
            this.$el.one('mousedown click', function () {
                cell.removeClass('has-error');
            });
            Espo.Ui.error(this.translate('wrongUsernamePasword', 'messages', 'User'));
        },
        
        onTimeout: function () {
            Espo.Ui.error(this.translate('timeout', 'messages', 'SecSign'));
                            
            $("#secSignDiv").addClass('hidden');
            $("#btn-login").removeClass('disabled');
            
            $("#secSignImage").attr('src', '');
        
            $("input[name='requestid']").val('');
            $("input[name='secsignid']").val('');
            $("input[name='authsessionid']").val('');
            $("input[name='servicename']").val('');
            $("input[name='serviceaddress']").val('');
        },

        showPasswordChangeRequest: function () {
            this.notify('Please wait...');
            this.createView('passwordChangeRequest', 'views/modals/password-change-request', {
                url: window.location.href
            }, function (view) {
                view.render();
                view.notify(false);
            });
        }
    });

});
