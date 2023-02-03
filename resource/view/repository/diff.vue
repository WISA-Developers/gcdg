<template>
    <div class="layoutCenter">
        <h2 class="title has_buttons" style="position: relative; ">
            <i class="xi-cloud-upload xi-1x"></i> {{ message }}
            <div class="buttons">
                <a href="#" @click.stop.prevent="setDesignMode('')" :class="{on: rendererName==''}"><i class="xi-layout-full-o"></i></a>
                <a href="#" @click.stop.prevent="setDesignMode('SideBySide')" :class="{on: rendererName=='SideBySide'}"><i class="xi-border-vertical"></i></a>
            </div>
        </h2>

		 <table class="table tableVertical">
			<colgroup>
				<col style="width:120px;">
				<col>
			</colgroup>
			<tbody>
			<tr>
				<th>리비전</th>
				<td>{{ rev }}</td>
			</tr>

			<tr>
				<th>작업자</th>
				<td>{{ author }}</td>
			</tr>
			<tr>
				<th>작업일시</th>
				<td>{{ date }}</td>
			</tr>
			</tbody>
		</table>

        <ul class="revision_files">
            <li v-for="(file, key) in files">
                <p>
					<a v-if="file.item=='modified'" href="#" @click.stop.prevent="diffSource(key)">
						<i :class="{['xi-'+file.kind]: true, 'xi-1x': true}"></i>
						<strong :class="{[file.item]: true}">[{{ file.item }}]</strong> 
						{{ file.path }}
					</a>
					<div v-if="file.item!='modified'">
						<i :class="{['xi-'+file.kind]: true, 'xi-1x': true}"></i>
						<strong :class="{[file.item]: true}">[{{ file.item }}]</strong> 
						{{ file.path }}
					</div>
				</p>
                <div class="differ" :id="'diff_'+key">

                </div>
            </li>
        </ul>
		<div class="bottom">

			<div class="button_block">
				<span class="button ok"><input type="button" value="패치파일 다운로드" @click="makePatch"></span>
				<span class="button"><input type="button" value="뒤로" @click="goList"></span>
			</div>
		</div>
    </div>
</template>

<script>
export default {
    setup: function () {
        return Vue.reactive({
            rev: null,
			author: null,
			date: null,
            message: null,
            files: [],
            current_diff: null,
            rendererName: window.localStorage.getItem('diff-rendererName') || ''
        });
    },
    methods: {
        diffSource: function(k, no_scroll)
        {
            api('/api/repository/diffSource/'+this.$route.params.rev+'?path='+encodeURIComponent(this.files[k].path)+'&rendererName='+this.rendererName)
                .then((ret) => {
                    if (ret.status == 'success') {
                        // 소스 출력
                        const cell = document.querySelector('#diff_' + k);
                        if (no_scroll) {
                            cell.innerHTML = ret.differ;
                        } else {
                            document.querySelectorAll('.differ:not(#diff_' + k + ')').forEach(function (o) {
                                o.innerHTML = '';
                            });
                            cell.innerHTML = (cell.innerHTML) ? '' : ret.differ;
                        }

						// table-fixed + colspan 사용 시 가로길이 컨트롤
						const table = document.querySelector('.diff-side-by-side > thead');
						if (table) {
							$(table).before(`
							<colgroup>
								<col style="width: 80px">
								<col style="width: 50%">
								<col style="width: 80px">
								<col style="width: 50%">
							</colgroup>
							`);
						}

                        this.current_diff = k;

                        // 스크롤 이동
                        if (no_scroll == true) return;
                        const top = $(cell).offset().top - 40;
                        if ($('.layoutHorizontal').css('display') == 'grid') {
                            $('.contentArea').animate({scrollTop: top + $('.contentArea').scrollTop() - $('.contentArea').offset().top});
                        } else {
                            $('html, body').animate({scrollTop: top});
                        }
                    }
                });
        },
		goList: function() {
			this.$router.go(-1);
		},
		makePatch: function() {
			api('/api/repository/patch/'+this.rev)
                .then((ret) => {
                   if (ret.status == 'success') {
                       const blob = new Blob([ret.data], {type: 'octet/stream'});

                       let a = document.createElement("a");
                       let url = window.URL.createObjectURL(blob);
                       document.body.appendChild(a);
                       a.href = url;
                       a.download = this.rev+'.patch';
                       a.click();
                       window.URL.revokeObjectURL(url);
                   }
                });
		},
        setDesignMode(mode) {
            this.rendererName = mode;
            window.localStorage.setItem('diff-rendererName', mode);

            if (this.current_diff > -1) {
                this.diffSource(this.current_diff, true);
            }

        },
    },
    beforeMount: function() {
		printLoading();
        api('/api/repository/diff/'+this.$route.params.rev)
            .then((ret) => {
                if (ret.status == 'success') {
                    this.rev = ret.data.rev;
					this.author = ret.data.author;
					this.date = ret.data.date;
                    this.message = ret.data.msg;
                    this.files = ret.data.files;

					if (ret.data.files.length > 0) {
						this.diffSource(0, true);
					}
                } else {
					location.href = '/';
				}
				removeLoading();
            });
    }
}
</script>