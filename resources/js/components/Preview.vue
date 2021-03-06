<template>
    <div>
        <heading class="mb-6">CSV Import</heading>

        <card class="flex flex-col" style="min-height: 300px">
            <div class="p-8">
                <h2 class="pb-4">Preview</h2>
                <p class="pb-4">
                    We were able to discover <b>{{ headings.length }}</b> column(s) and <b>{{ total_rows }}</b>
                    row(s) in your data.
                </p>
                <p class="pb-4">
                    Choose a resource to import them into and match up the headings from the CSV to the
                    appropriate fields of the resource.
                </p>

                <h2 class="py-4">Resource</h2>
                <p class="pb-4">Choose which resource to import your data into:</p>
                <div>
                    <select name="resource" class="block form-control form-select" v-model="resource">
                        <option value="">- Select a resource -</option>
                        <option v-for="(label, index) in resources" :value="index">{{ label }}</option>
                    </select>
                </div>
            </div>

            <table class="table w-full">
                <thead>
                    <tr>
                        <th v-for="heading in headings">{{ heading }}</th>
                    </tr>
                </thead>
                <tbody>
                    <tr>
                        <td v-for="heading in headings" class="text-center">
                            <select class="w-full form-control form-select" v-model="mappings[heading]">
                                <option value="">- Ignore this column -</option>
                                <option v-for="field in fields[resource]" :value="field.attribute">{{ field.name }}</option>
                            </select>
                        </td>
                    </tr>
                    <tr v-for="row in rows">
                        <td v-for="col in row">{{ col }}</td>
                    </tr>
                </tbody>
            </table>

            <div class="flex border-b border-40">
                <div class="w-1/5 px-8 py-6">
                    <label for="use_validation" class="inline-block text-80 pt-2 leading-tight">리소스 유효성 검사 규칙 사용</label>
                </div>
                <div class="py-6 px-8 w-4/5">
                    <input v-model="use_validation" type="checkbox" class="checkbox mt-2" id="use_validation" name="use_validation">
                    <div class="help-text help-text mt-2">
                        CSV 헤더의 컬럼명이 리소스 컬럼명과 다르면 데이터 유효성 검사가 제대로 동작하지 않습니다.
                    </div>
                </div>
            </div>

            <div class="bg-30 flex px-8 py-4">
                <!--<button class="btn btn-default">&leftarrow; Cancel</button>-->
                <button class="btn btn-default btn-primary" @click="runImport" :disabled="disabledImport" id="run-import">Import &rightarrow; </button>
            </div>
        </card>
    </div>
</template>

<script>
export default {
    mounted() {
        const self = this;

        Nova.request()
            .get('/nova-vendor/laravel-nova-csv-import/preview/' + this.file)
            .then(function (response) {
                self.headings = response.data.headings;
                self.rows = response.data.sample;
                self.resources = response.data.resources;
                self.total_rows = response.data.total_rows;
                self.fields = response.data.fields;

                self.headings.forEach(function (heading) {
                    self.$set(self.mappings, heading, "");
                });
            });
    },
    data() {
        return {
            headings: [],
            rows: [],
            resources: [],
            fields: [],
            resource: '',
            mappings: {},
            use_validation: false,
        };
    },
    props: [
        'file'
    ],
    watch: {
        resource : function (resource) {
            const self = this;

            // Reset all of the headings to blanks
            this.headings.forEach(function (heading) {
                self.$set(self.mappings, heading, "");
            });

            if (resource === "") {
                return;
            }

            // For each field of the resource, try to find a matching heading and pre-assign
            this.fields[resource].forEach(function (field_config) {
                let field = field_config.attribute,
                    heading_index = self.headings.indexOf(field);

                if (heading_index < 0) {
                    return;
                }

                let heading = self.headings[heading_index];

                if (heading === field) {
                    self.$set(self.mappings, heading, field);
                }
            });
        }
    },
    methods: {
        runImport: function () {
            const self = this;

            if (! this.hasValidConfiguration()) {
                return;
            }

            const button = document.getElementById('run-import');
            button.innerHTML = 'Importing...';
            button.setAttribute("disabled", "disabled");

            let data = {
                resource: this.resource,
                mappings: this.mappings,
                use_validation: this.use_validation,
            };

            Nova.request()
                .post(this.url('import/' + this.file), data)
                .then(function (response) {
                    if (response.data.result === 'success') {
                        self.$toasted.show('All data imported!', {type: "success"});
                        self.$router.push({name: 'csv-import-review', params: {file: self.file, resource: self.resource}});
                    } else {
                        button.innerHTML = 'Import &rightarrow;';
                        button.removeAttribute("disabled");
                        self.$toasted.show('There were problems importing some of your data', {type: "error"});
                    }
                });

            // this.$router.push({name: 'csv-import-review', params: {file: this.file, resource: this.resource}});
        },
        hasValidConfiguration: function () {
            const mappedColumns = [],
                mappings = this.mappings;

            Object.keys(mappings).forEach(function (key) {
                if (mappings[key] !== "") {
                    mappedColumns.push(key);
                }
            });

            return this.resource !== '' && mappedColumns.length > 0;
        },
        url: function (path) {
            return '/nova-vendor/laravel-nova-csv-import/' + path;
        }
    },
    computed: {
        disabledImport: function () {
            return ! this.hasValidConfiguration();
        },
    }
}
</script>

<style>
/* Scoped Styles */
</style>
