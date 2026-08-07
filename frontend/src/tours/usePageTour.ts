import { useCallback, useEffect, useRef } from 'react'
import { driver, type DriveStep } from 'driver.js'
import { useTourSettings } from './TourContext'
import { isTourSeen, markTourSeen } from './tourStorage'

/**
 * Recorrido guiado de una pantalla. Arranca solo la primera vez que se entra (si los
 * recorridos están activados) y expone `start` para volver a verlo a demanda (botón
 * "Ver recorrido"). `skipMissingElement` deja pasar de largo un paso cuyo elemento no
 * está en el DOM en ese momento (por ejemplo, un aviso que solo aparece si hay datos).
 */
export function usePageTour(id: string, steps: DriveStep[]) {
  const { enabled } = useTourSettings()
  const autoStartedRef = useRef(false)

  const start = useCallback(() => {
    if (steps.length === 0) return
    driver({
      showProgress: true,
      allowClose: true,
      skipMissingElement: true,
      overlayOpacity: 0.6,
      nextBtnText: 'Siguiente',
      prevBtnText: 'Anterior',
      doneBtnText: 'Listo',
      progressText: '{{current}} de {{total}}',
      steps,
    }).drive()
    markTourSeen(id)
  }, [id, steps])

  useEffect(() => {
    if (!enabled || autoStartedRef.current || isTourSeen(id)) return
    autoStartedRef.current = true
    // Esperar a que la pantalla (y los elementos referenciados por el recorrido) terminen de montarse.
    const t = window.setTimeout(start, 500)
    return () => window.clearTimeout(t)
    // eslint-disable-next-line react-hooks/exhaustive-deps
  }, [enabled, id])

  return { start }
}
