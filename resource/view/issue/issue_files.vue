<template>
    <table id="component_files" class="table tableHorizontal flexible">
        <colgroup>
            <col>
            <col>
            <col>
            <col style="width: 70%">
        </colgroup>
        <thead>
        <tr>
            <th>작성일시</th>
            <th>작성자</th>
            <th>크기</th>
            <th>파일명</th>
        </tr>
		</thead>
        <tbody>
        <tr v-for="file in files">
            <td class="date">{{ file.registerd }}</td>
            <td>{{ file.creater.name }}</td>
            <td>{{ file.filesize_human }}</td>
            <td class="left content" style="padding-right: 30px">
                <a :href="'/api/file/download/'+file.idx" @click.prevent="read(file)">
					<i v-if="file.uploader=='editor'" class="xi-code attr_icon"></i>
					{{ file.origin }}
				</a>
				<div class="buttons">
					<a href="#" @click.prevent="remove(file.idx)"><i class="xi-trash"></i></a>
				</div>
            </td>
        </tr>					
        </tbody>
    </table>
    <div class="bottom">
        <div class="page_block" v-html="paginator"></div>
        <div class="button_block">
            <span class="button ok"><input type="button" value="업로드" @click="upload"></span>
        </div>
    </div>
</template>

<script>
export default {
	data: function() {
		return {
			page: 0,
			paginator: null,
			files: []
		}
	},
	props: {
		data: Array
	},
	methods: {
		hashchanged: function() {
			this.reload()
		},
		reload: function() {
			const uri = new URL(location.href);
			const suri = new URL(uri.origin+'?'+uri.hash.split('?').at(1));
			const page = suri.searchParams.get('f') || 1;

			api('/api/file/list/'+page+'?hash='+this.data.hash+'&limit=5')
				.then((ret) => {
					if (ret.status == 'success') {
						if (ret.count > 0 && ret.data.length == 0) { // 삭제 후 현재 페이지 없을 경우
							ret.page--;
							sethash({f: ret.page, l: 'f'});
							return false;
						}

						this.page = ret.page;
						this.paginator = ret.paginator;
						this.files = ret.data;

						this.$emit('get-data', 'component_file_count', ret.count);
					}
				});
		},
		upload: function() {
			const self = this;
			const file = document.createElement('input');
			file.type = 'file';
			file.multiple = true;
			file.click();

			file.addEventListener('change', function() {
				window.total_files = this.files.length;
				window.current_files = 0;

				for(let n = 0; n < this.files.length; n++) {
					ajaxUpload({hash: self.data.hash, uploader: 'upload', referer: 'files'}, this.files[n], function() {
						window.current_files++;
						if (window.total_files == window.current_files) {
							sethash({f: 1, l: 'f'});
							self.reload();
						}
					});
				}
			});

			return;
		},
		remove: function(idx) {
			if (!window.confirm('선택하신 파일을 삭제하시겠습니까?')) {
				return false;
			}

			api('/api/file/remove/'+idx)
			.then((ret) => {
				if (ret.status == 'success') {
					sethash({l: 'f'});
					this.reload();
				}
			});

			//로딩 후 스크롤 이동
			if (this.data.scrollNext && this.data.scrollNext == 'f') {
				let o = $('#component_files');
				$('.contentArea').animate({
					'scrollTop': o.offset().top+$('.contentArea').scrollTop()-$('.contentArea').offset().top-10
				});
			}
		},
		read: function(file) {
			const ext = file.mime.toLowerCase();
			if (['jpg', 'jpeg', 'png', 'gif', 'webp'].includes(ext)) {
				imageViewer('/api/file/download/'+file.idx);
			} else {
				if (confirm('파일을 다운로드 하시겠습니까?')) {
					location .href = '/api/file/download/'+file.idx+'?token='+window.localStorage.getItem('token');
				}
			}
			return true;
		}
	},
	mounted: function() {
		this.reload();
	}
}
</script>