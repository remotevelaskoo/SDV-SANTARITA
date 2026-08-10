import { createServerFn } from "@tanstack/react-start";
import { z } from "zod";

/**
 * Simulação de validação biométrica facial.
 * Em um cenário real, isso integraria com serviços como Azure Face API, AWS Rekognition
 * ou uma biblioteca local de processamento de imagem (WASM/Edge).
 */
export const validarBiometriaFacial = createServerFn({ method: "POST" })
  .inputValidator((data) => z.object({
    imagemBase64: z.string(),
    pessoaId: z.string().optional(),
    tipo: z.enum(["morador", "visitante", "prestador"])
  }).parse(data))
  .handler(async ({ data }) => {
    // Simulação de latência de rede/processamento
    await new Promise((resolve) => setTimeout(resolve, 1200));

    // Lógica de simulação de "match"
    // Em modo demo, aceitamos 95% das vezes se o ID for fornecido
    const score = data.pessoaId ? 0.92 + Math.random() * 0.07 : 0.45 + Math.random() * 0.3;
    const sucesso = score > 0.85;

    return {
      sucesso,
      score,
      detalhes: sucesso 
        ? "Identidade confirmada com alta confiança." 
        : "Não foi possível confirmar a identidade facial.",
      timestamp: new Date().toISOString()
    };
  });
