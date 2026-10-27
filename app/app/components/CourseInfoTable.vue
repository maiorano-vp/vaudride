<script setup lang="ts">
export interface InfoRow {
  icon: string
  label: string
  value: string
  /** A link on the value (a section of the page): shown in green and underlined, as on vaud.ch. */
  href?: string
}

defineProps<{ rows: InfoRow[] }>()
</script>

<template>
  <table class="info-table">
    <tbody>
      <tr v-for="row in rows" :key="row.label">
        <th scope="row">
          <UIcon :name="row.icon" class="icon" />
          {{ row.label }}
        </th>
        <td>
          <a v-if="row.href" :href="row.href">{{ row.value }}</a>
          <template v-else>{{ row.value }}</template>
        </td>
      </tr>
    </tbody>
  </table>
</template>

<style scoped>
/* the practical-information table of vaud.ch: every other row tinted, a hairline between rows */
.info-table {
  width: 100%;
  font-size: 1.125rem;
  border-collapse: collapse;
}
tr {
  border-bottom: 1px solid rgb(194 209 217 / 50%);
}
tr:nth-child(odd) {
  background: #f2f5f7;
}
th,
td {
  padding: 0.85rem 1rem;
  text-align: left;
  vertical-align: top;
}
th {
  width: 40%;
  font-weight: 700;
  color: var(--color-vaud-800);
}
.icon {
  margin-right: 0.5rem;
  vertical-align: -0.15em;
  color: var(--color-vaud-600);
}
td a {
  color: var(--color-vaud-green-700, #137a5d);
  text-decoration: underline;
}
</style>
