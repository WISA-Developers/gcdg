if (window.localStorage.getItem('site-darkmode') == 'Y') {
    document.querySelector('body').classList.add('darkmode');
}

const current_project_idx = window.localStorage.getItem('current_project_idx');
const token  = window.localStorage.getItem('token') || '';
fetch('/api/staff/me?current_project_idx='+current_project_idx+'&token='+token)
    .then((response) => response.json())
    .then((me) => {

        // 로그인
        if (me.status != 'success') {
            if ((new URL(location.href)).host == 'gcdg.zardsama.net') {
                location.href = '/testlogin.php';
            } else {
                location.href = 'https://wep2.wisa.co.kr/login?login_url=issue';
            }
            return;
        }

        // 설정
        window.localStorage.setItem('token', me.token);

        const routes = [
            {path: '/', component: () => loadModule('/dashboard/index.vue', options)},
            {name: 'dashboard', path: '/dashboard/:project_idx?', component: () => loadModule('/dashboard/index.vue', options)},
            {path: '/issue', component: () => loadModule('/issue/index.vue', options)},
            {path: '/:project_idx/issue', component: () => loadModule('/issue/index.vue', options)},
            {path: '/issue/view/:idx', component: () => loadModule('/issue/view.vue', options)},
            {path: '/:project_idx/issue/view/:idx', component: () => loadModule('/issue/view.vue', options)},
            {path: '/issue/ticket', component: () => loadModule('/issue/ticket.vue', options)},
            {path: '/:project_idx/issue/ticket', component: () => loadModule('/issue/ticket.vue', options)},
            {path: '/issue/ticket/:idx', component: () => loadModule('/issue/ticket.vue', options)},
            {path: '/schedule', component: () => loadModule('/schedule/index.vue', options)},
            {path: '/repository', component: () => loadModule('/repository/index.vue', options)},
            {path: '/repository/:rev', component: () => loadModule('/repository/diff.vue', options)},
            {path: '/explorer', component: () => loadModule('/explorer/index.vue', options)},
            {path: '/welease', component: () => loadModule('/welease/index.vue', options)},
            {path: '/project', component: () => loadModule('/project/list.vue', options)},
            {path: '/project/create', component: () => loadModule('/project/create.vue', options)},
            {path: '/project/create/:idx', component: () => loadModule('/project/create.vue', options)},
            {name: 'project_select', path: '/project/select', component: () => loadModule('/project/select.vue', options)},
            {path: '/mypage', component: () => loadModule('/mypage/index.vue', options)}
        ];

        const router = VueRouter.createRouter({
            history: VueRouter.createWebHashHistory(),
            // stringifyQuery: (r) => {},
            routes,
        });

        const app = Vue.createApp({
            provide: {
                define_config: me.define,
                staff_snapshot: me.staff_snapshot
            },
            data: function() {
                return {
                    staff: me.staff
                }
            },
            watch: {
                $route(to, from) {
                    this.hashchanged();
                }
            },
            computed: {
                project_idx: function() {
                    return this.$route.params.project_idx || current_project_idx;
                }
            },
            methods: {
                hashchanged: function() {
                    if (typeof this.$refs.content.hashchanged == 'function') {
                        this.$refs.content.hashchanged();
                    }
                },
                mypage: function() {
                    router.push({
                        path: '/mypage'
                    });
                },
                projectSelect: function(project_idx) {
                    window.localStorage.setItem('current_project_idx', project_idx);
                    api('/api/staff/me?current_project_idx=' + project_idx)
                        .then((me) => {
                            this.staff = me.staff;
                            if (this.$route.name == 'dashboard') {
                                //router.go(0);
                            } else {
                                this.$router.push({
                                    path: '/dashboard/' + project_idx
                                });
                            }
                        });
                }
            }
        });
        app.use(router);
        app.mount('#app');
    });