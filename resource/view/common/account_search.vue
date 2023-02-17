<template>
    <div class="input_auto" @click.stop="focus">
        <span>
            <label v-for="account in selected" @click="remove">
                {{ account.site_name }}
                <span v-if="!account.site_name">{{ account.biz_name }}</span>
                <input type="hidden" :name="name+'[]'" :value="account.idx">
            </label>
        </span>
        <input type="text" class="input_text" @input="find" @keydown="keyControl" @blur="hide">
        <ul class="lists">
            <li
                v-for="(account, idx) in searched"
                :class="{select: (idx==0) ? true : false}"
                @click.stop.prevent="itemClick"
            >
                <revision>
                    <strong>{{ account.account_id }}</strong> {{ account.site_name }}
                </revision>
            </li>
        </ul>
    </div>
</template>

<script type="module">
export default {
    data: function() {
        return {
            selected: [], // 선택된 데이터
            searched: []  // 검색중인 데이터
        }
    },
    props: {
        name: String,			// input field 명
        source: String,			// 조회 API
        search: Array,			// default value
    },
    watch: {
        search: function() {
            this.reload();
        }
    },
    methods: {
        reload: function() {
            this.selected = this.search || [];
        },
        focus(e) {
            if (e.target.tagName != 'INPUT') {
                $(e.currentTarget).find('input[type=text]').focus();
            }
        },
        find(e) {
            const input = e.target;
            if (input.value) {
                if (window.searchDelay) {
                    clearTimeout(window.searchDelay);
                }

                if (!input.old_value || input.old_value != input.value) {
                    input.old_value = input.value;
                    window.searchDelay = setTimeout(() => {
                        api('/api/'+this.source, 'get', {'search_str': input.value})
                            .then((ret) => {
                                this.searched = ret.data;

                                if (ret.data.length > 0) {
                                    const list = this.$el.querySelector('.lists');
                                    $(list).fadeIn(50);
                                    list.style.top = (this.$el.offsetHeight-2);
                                    list.style.display = 'block';
                                    list.scrollTop = 0;
                                    window.search_cursor = 0;
                                } else {
                                    $('.lists', this.$el).hide();
                                }
                            });
                    }, 200);
                }
            } else {
                this.hide(e);
            }
        },
        keyControl(e) {
            const list = this.$el.querySelector('.lists');
            const items = list.querySelectorAll('li');

            if (e.keyCode == 38) {
                e.preventDefault();
                if (window.search_cursor <= 0) {
                    return;
                }
                window.search_cursor--;
            } else if (e.keyCode == 40) {
                e.preventDefault();
                if (window.search_cursor >= items.length-1) {
                    return;
                }
                window.search_cursor++;
            } else if (e.keyCode == 13) {
                e.preventDefault();
                if (e.target.value == '') {
                    return true;
                }
                this.itemSelect(e);
                return;
            } else {
                return false;
            }

            items.forEach(function(item) {
                item.classList.remove('select');
            });
            items.item(window.search_cursor).classList.add('select');

            // 커서에 따라 스크롤이 가운데로 위치
            const selected = list.querySelector('.select');
            list.scrollTop =
                (selected.getBoundingClientRect()).top
                + (list.scrollTop - ((list.getBoundingClientRect()).top *1.4));
        },
        itemClick(e) {
            window.search_cursor = $(e.currentTarget).index();
            this.itemSelect(e);
        },
        itemSelect(e) {
            const list = this.$el.querySelector('.lists');
            const selected = list.querySelectorAll('li').item(window.search_cursor);
            const input = this.$el.querySelector('input[type=text]');

            // 중복 체크
            const exists = (this.selected.some((s) => {
                if (s.idx == this.searched[window.search_cursor].idx) return true;
            }));
            if (!exists) {
                if (typeof window.search_cursor == 'undefined') return;
                if (typeof this.searched[window.search_cursor] != 'undefined') {
                    this.selected.push(
                        this.searched[window.search_cursor]
                    );
                }
            }

            // 초기화
            window.search_cursor = 0;
            input.value = '';
            input.focus();
            $(list).fadeOut(50);
        },
        remove(e) {
            const account_idx = e.currentTarget.querySelector('input').value;
            this.selected.forEach((account, idx) => {
                if (account.idx == account_idx) {
                    this.selected.splice(idx, 1);
                }
            });
        },
        hide(e)
        {
            const input = this.$el.querySelector('input[type=text]');
            $('.lists', this.$el).fadeOut(100, function() {
                this.searched = [];
                input.value = '';
                window.search_cursor = 0;
            });
        }
    },
    mounted: function() {
        this.reload();
    },
    updated: function() {
        if (this.search && this.search.length != this.selected.length) {
            let selected = [];
            this.selected.forEach(function(staff) {
                selected.push(staff.idx);
            });
            this.$emit('get-search', this.name, selected);
        }
    }
}
</script>