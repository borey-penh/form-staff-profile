/**
 * Shared edit support for the request forms — e.g. /leave?edit=12, /travel?edit=5.
 *
 * When the page is opened with an `edit` query param, the request's saved data
 * is loaded for prefill and `save()` routes to the update endpoint instead of
 * creating a new request. Editing is only offered while nobody has approved,
 * rejected or completed the request (enforced by the API as well).
 */
import { ref } from 'vue'
import { useRoute } from 'vue-router'
import { getRequest, submitRequest, updateRequest } from '@/services/portalService'
import { useToastStore } from '@/stores/toast'

export function useRequestEdit(type) {
  const route = useRoute()
  const toast = useToastStore()

  const editId = ref(null)
  const editStatus = ref('')
  const currentAttachment = ref(null) // URL of the saved attachment, if any
  const loading = ref(false)

  /** Fetch the request to edit (no-op without ?edit=) and prefill via apply(). */
  async function loadForEdit(apply) {
    const id = Number(route.query.edit)
    if (!id) return null
    loading.value = true
    try {
      const res = await getRequest(id)
      if (res.request.type !== type) {
        toast.show(`Request #${id} is a ${res.request.type} request.`)
        return null
      }
      if (res.editable === false) {
        toast.show('This request was already approved or rejected and can no longer be edited.')
        return null
      }
      editId.value = res.request.id
      editStatus.value = res.request.status
      currentAttachment.value = res.form?.filePath ? '/storage/' + res.form.filePath : null
      apply?.(res.form ?? {})
      return res.form ?? {}
    } catch (e) {
      toast.show(e.message)
      return null
    } finally {
      loading.value = false
    }
  }

  /** Save through submit or update depending on the edit mode. */
  async function save(data, file) {
    if (editId.value) return updateRequest(editId.value, type, data, file)
    return submitRequest(type, data, file)
  }

  return { editId, editStatus, currentAttachment, loading, loadForEdit, save }
}
