<template>
  <div>
    <div class="row q-mt-md">
      <div class="col-2"></div>
      <div class="col-8">
        <div @click="sendData">
          <q-checkbox dense v-model="enableBusinessTenant">
            <q-item-label caption>
              {{ $t('COREUSERGROUPSLIMITS.LABEL_ITS_BUSINESS_TENANT') }}
            </q-item-label>
          </q-checkbox>
        </div>
      </div>
    </div>
    <div class="row q-my-sm">
      <div class="col-2"></div>
      <div class="col-8">
        <q-item-label caption>
          {{ $t('COREUSERGROUPSLIMITS.HINT_ITS_BUSINESS_TENANT_HTML') }}
        </q-item-label>
      </div>
    </div>
    <div class="row">
      <div class="col-2"></div>
      <div class="col-8">
        <q-checkbox dense v-model="enableGroupWare">
          <span v-t="'COREUSERGROUPSLIMITS.LABEL_ENABLE_GROUPWARE'"/>
        </q-checkbox>
      </div>
    </div>
  </div>
</template>

<script>
export default {
  name: 'EditBusinessTenant',
  data () {
    return {
      enableBusinessTenant: false,
      enableGroupWare: false,
    }
  },
  watch: {
    enableBusinessTenant () {
      this.sendData()
    },
    enableGroupWare () {
      this.sendData()
    }
  },
  methods: {
    sendData () {
      this.$emit('updateParent', {
        enableBusinessTenant: this.enableBusinessTenant,
        enableGroupWare: this.enableGroupWare,
      })
    }
  }
}
</script>

<style scoped>

</style>
