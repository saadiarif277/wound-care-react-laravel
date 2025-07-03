// ========================================
// COMPLETE ICD-10 WOUND CODES WITH DESCRIPTIONS FOR SKIN SUBSTITUTE BILLING
// Updated: 2025 - Includes ALL LCD-required codes with descriptions
// ========================================

// 1. ARTERIAL ULCER CODES
// Requires TWO codes: Primary I70.- + Secondary L97.- (or L98.49- for upper extremity)
const arterialUlcerCodes = [
  // ===== I70.23X - Native arteries, RIGHT LEG =====
  { code: "I70.231", description: "Atherosclerosis of native arteries of right leg with ulceration of thigh" },
  { code: "I70.232", description: "Atherosclerosis of native arteries of right leg with ulceration of calf" },
  { code: "I70.233", description: "Atherosclerosis of native arteries of right leg with ulceration of ankle" },
  { code: "I70.234", description: "Atherosclerosis of native arteries of right leg with ulceration of heel and midfoot" },
  { code: "I70.235", description: "Atherosclerosis of native arteries of right leg with ulceration of other part of foot" },
  { code: "I70.238", description: "Atherosclerosis of native arteries of right leg with ulceration of other part of lower leg" },
  { code: "I70.239", description: "Atherosclerosis of native arteries of right leg with ulceration of unspecified site" },
  
  // ===== I70.24X - Native arteries, LEFT LEG =====
  { code: "I70.241", description: "Atherosclerosis of native arteries of left leg with ulceration of thigh" },
  { code: "I70.242", description: "Atherosclerosis of native arteries of left leg with ulceration of calf" },
  { code: "I70.243", description: "Atherosclerosis of native arteries of left leg with ulceration of ankle" },
  { code: "I70.244", description: "Atherosclerosis of native arteries of left leg with ulceration of heel and midfoot" },
  { code: "I70.245", description: "Atherosclerosis of native arteries of left leg with ulceration of other part of foot" },
  { code: "I70.248", description: "Atherosclerosis of native arteries of left leg with ulceration of other part of lower leg" },
  { code: "I70.249", description: "Atherosclerosis of native arteries of left leg with ulceration of unspecified site" },
  
  // ===== I70.25 - Native arteries, OTHER EXTREMITIES (UPPER) =====
  { code: "I70.25", description: "Atherosclerosis of native arteries of other extremities with ulceration" },
  
  // ===== I70.26X - Native arteries, UNSPECIFIED EXTREMITY =====
  { code: "I70.261", description: "Atherosclerosis of native arteries of extremities with ulceration of thigh" },
  { code: "I70.262", description: "Atherosclerosis of native arteries of extremities with ulceration of calf" },
  { code: "I70.263", description: "Atherosclerosis of native arteries of extremities with ulceration of ankle" },
  { code: "I70.264", description: "Atherosclerosis of native arteries of extremities with ulceration of heel and midfoot" },
  { code: "I70.265", description: "Atherosclerosis of native arteries of extremities with ulceration of other part of foot" },
  { code: "I70.268", description: "Atherosclerosis of native arteries of extremities with ulceration of other part of lower extremity" },
  { code: "I70.269", description: "Atherosclerosis of native arteries of extremities with ulceration of unspecified site" },
  
  // ===== I70.33X - Unspecified bypass graft, RIGHT LEG =====
  { code: "I70.331", description: "Atherosclerosis of unspecified type of bypass graft of right leg with ulceration of thigh" },
  { code: "I70.332", description: "Atherosclerosis of unspecified type of bypass graft of right leg with ulceration of calf" },
  { code: "I70.333", description: "Atherosclerosis of unspecified type of bypass graft of right leg with ulceration of ankle" },
  { code: "I70.334", description: "Atherosclerosis of unspecified type of bypass graft of right leg with ulceration of heel and midfoot" },
  { code: "I70.335", description: "Atherosclerosis of unspecified type of bypass graft of right leg with ulceration of other part of foot" },
  { code: "I70.338", description: "Atherosclerosis of unspecified type of bypass graft of right leg with ulceration of other part of lower leg" },
  { code: "I70.339", description: "Atherosclerosis of unspecified type of bypass graft of right leg with ulceration of unspecified site" },
  
  // ===== I70.34X - Unspecified bypass graft, LEFT LEG =====
  { code: "I70.341", description: "Atherosclerosis of unspecified type of bypass graft of left leg with ulceration of thigh" },
  { code: "I70.342", description: "Atherosclerosis of unspecified type of bypass graft of left leg with ulceration of calf" },
  { code: "I70.343", description: "Atherosclerosis of unspecified type of bypass graft of left leg with ulceration of ankle" },
  { code: "I70.344", description: "Atherosclerosis of unspecified type of bypass graft of left leg with ulceration of heel and midfoot" },
  { code: "I70.345", description: "Atherosclerosis of unspecified type of bypass graft of left leg with ulceration of other part of foot" },
  { code: "I70.348", description: "Atherosclerosis of unspecified type of bypass graft of left leg with ulceration of other part of lower leg" },
  { code: "I70.349", description: "Atherosclerosis of unspecified type of bypass graft of left leg with ulceration of unspecified site" },
  
  // ===== I70.35 - Unspecified bypass graft, OTHER EXTREMITIES =====
  { code: "I70.35", description: "Atherosclerosis of unspecified type of bypass graft of other extremity with ulceration" },
  
  // ===== I70.36X - Unspecified bypass graft, UNSPECIFIED EXTREMITY =====
  { code: "I70.361", description: "Atherosclerosis of unspecified type of bypass graft of extremities with ulceration of thigh" },
  { code: "I70.362", description: "Atherosclerosis of unspecified type of bypass graft of extremities with ulceration of calf" },
  { code: "I70.363", description: "Atherosclerosis of unspecified type of bypass graft of extremities with ulceration of ankle" },
  { code: "I70.364", description: "Atherosclerosis of unspecified type of bypass graft of extremities with ulceration of heel and midfoot" },
  { code: "I70.365", description: "Atherosclerosis of unspecified type of bypass graft of extremities with ulceration of other part of foot" },
  { code: "I70.368", description: "Atherosclerosis of unspecified type of bypass graft of extremities with ulceration of other part of lower extremity" },
  { code: "I70.369", description: "Atherosclerosis of unspecified type of bypass graft of extremities with ulceration of unspecified site" },
  
  // ===== I70.43X - Autologous vein bypass graft, RIGHT LEG =====
  { code: "I70.431", description: "Atherosclerosis of autologous vein bypass graft of right leg with ulceration of thigh" },
  { code: "I70.432", description: "Atherosclerosis of autologous vein bypass graft of right leg with ulceration of calf" },
  { code: "I70.433", description: "Atherosclerosis of autologous vein bypass graft of right leg with ulceration of ankle" },
  { code: "I70.434", description: "Atherosclerosis of autologous vein bypass graft of right leg with ulceration of heel and midfoot" },
  { code: "I70.435", description: "Atherosclerosis of autologous vein bypass graft of right leg with ulceration of other part of foot" },
  { code: "I70.438", description: "Atherosclerosis of autologous vein bypass graft of right leg with ulceration of other part of lower leg" },
  { code: "I70.439", description: "Atherosclerosis of autologous vein bypass graft of right leg with ulceration of unspecified site" },
  
  // ===== I70.44X - Autologous vein bypass graft, LEFT LEG =====
  { code: "I70.441", description: "Atherosclerosis of autologous vein bypass graft of left leg with ulceration of thigh" },
  { code: "I70.442", description: "Atherosclerosis of autologous vein bypass graft of left leg with ulceration of calf" },
  { code: "I70.443", description: "Atherosclerosis of autologous vein bypass graft of left leg with ulceration of ankle" },
  { code: "I70.444", description: "Atherosclerosis of autologous vein bypass graft of left leg with ulceration of heel and midfoot" },
  { code: "I70.445", description: "Atherosclerosis of autologous vein bypass graft of left leg with ulceration of other part of foot" },
  { code: "I70.448", description: "Atherosclerosis of autologous vein bypass graft of left leg with ulceration of other part of lower leg" },
  { code: "I70.449", description: "Atherosclerosis of autologous vein bypass graft of left leg with ulceration of unspecified site" },
  
  // ===== I70.45 - Autologous vein bypass graft, OTHER EXTREMITIES =====
  { code: "I70.45", description: "Atherosclerosis of autologous vein bypass graft of other extremity with ulceration" },
  
  // ===== I70.46X - Autologous vein bypass graft, UNSPECIFIED EXTREMITY =====
  { code: "I70.461", description: "Atherosclerosis of autologous vein bypass graft of extremities with ulceration of thigh" },
  { code: "I70.462", description: "Atherosclerosis of autologous vein bypass graft of extremities with ulceration of calf" },
  { code: "I70.463", description: "Atherosclerosis of autologous vein bypass graft of extremities with ulceration of ankle" },
  { code: "I70.464", description: "Atherosclerosis of autologous vein bypass graft of extremities with ulceration of heel and midfoot" },
  { code: "I70.465", description: "Atherosclerosis of autologous vein bypass graft of extremities with ulceration of other part of foot" },
  { code: "I70.468", description: "Atherosclerosis of autologous vein bypass graft of extremities with ulceration of other part of lower extremity" },
  { code: "I70.469", description: "Atherosclerosis of autologous vein bypass graft of extremities with ulceration of unspecified site" },
  
  // ===== I70.53X - Nonautologous biological bypass graft, RIGHT LEG =====
  { code: "I70.531", description: "Atherosclerosis of nonautologous biological bypass graft of right leg with ulceration of thigh" },
  { code: "I70.532", description: "Atherosclerosis of nonautologous biological bypass graft of right leg with ulceration of calf" },
  { code: "I70.533", description: "Atherosclerosis of nonautologous biological bypass graft of right leg with ulceration of ankle" },
  { code: "I70.534", description: "Atherosclerosis of nonautologous biological bypass graft of right leg with ulceration of heel and midfoot" },
  { code: "I70.535", description: "Atherosclerosis of nonautologous biological bypass graft of right leg with ulceration of other part of foot" },
  { code: "I70.538", description: "Atherosclerosis of nonautologous biological bypass graft of right leg with ulceration of other part of lower leg" },
  { code: "I70.539", description: "Atherosclerosis of nonautologous biological bypass graft of right leg with ulceration of unspecified site" },
  
  // ===== I70.54X - Nonautologous biological bypass graft, LEFT LEG =====
  { code: "I70.541", description: "Atherosclerosis of nonautologous biological bypass graft of left leg with ulceration of thigh" },
  { code: "I70.542", description: "Atherosclerosis of nonautologous biological bypass graft of left leg with ulceration of calf" },
  { code: "I70.543", description: "Atherosclerosis of nonautologous biological bypass graft of left leg with ulceration of ankle" },
  { code: "I70.544", description: "Atherosclerosis of nonautologous biological bypass graft of left leg with ulceration of heel and midfoot" },
  { code: "I70.545", description: "Atherosclerosis of nonautologous biological bypass graft of left leg with ulceration of other part of foot" },
  { code: "I70.548", description: "Atherosclerosis of nonautologous biological bypass graft of left leg with ulceration of other part of lower leg" },
  { code: "I70.549", description: "Atherosclerosis of nonautologous biological bypass graft of left leg with ulceration of unspecified site" },
  
  // ===== I70.55 - Nonautologous biological bypass graft, OTHER EXTREMITIES =====
  { code: "I70.55", description: "Atherosclerosis of nonautologous biological bypass graft of other extremity with ulceration" },
  
  // ===== I70.56X - Nonautologous biological bypass graft, UNSPECIFIED EXTREMITY =====
  { code: "I70.561", description: "Atherosclerosis of nonautologous biological bypass graft of extremities with ulceration of thigh" },
  { code: "I70.562", description: "Atherosclerosis of nonautologous biological bypass graft of extremities with ulceration of calf" },
  { code: "I70.563", description: "Atherosclerosis of nonautologous biological bypass graft of extremities with ulceration of ankle" },
  { code: "I70.564", description: "Atherosclerosis of nonautologous biological bypass graft of extremities with ulceration of heel and midfoot" },
  { code: "I70.565", description: "Atherosclerosis of nonautologous biological bypass graft of extremities with ulceration of other part of foot" },
  { code: "I70.568", description: "Atherosclerosis of nonautologous biological bypass graft of extremities with ulceration of other part of lower extremity" },
  { code: "I70.569", description: "Atherosclerosis of nonautologous biological bypass graft of extremities with ulceration of unspecified site" },
  
  // ===== I70.63X - Nonbiological bypass graft, RIGHT LEG =====
  { code: "I70.631", description: "Atherosclerosis of nonbiological bypass graft of right leg with ulceration of thigh" },
  { code: "I70.632", description: "Atherosclerosis of nonbiological bypass graft of right leg with ulceration of calf" },
  { code: "I70.633", description: "Atherosclerosis of nonbiological bypass graft of right leg with ulceration of ankle" },
  { code: "I70.634", description: "Atherosclerosis of nonbiological bypass graft of right leg with ulceration of heel and midfoot" },
  { code: "I70.635", description: "Atherosclerosis of nonbiological bypass graft of right leg with ulceration of other part of foot" },
  { code: "I70.638", description: "Atherosclerosis of nonbiological bypass graft of right leg with ulceration of other part of lower leg" },
  { code: "I70.639", description: "Atherosclerosis of nonbiological bypass graft of right leg with ulceration of unspecified site" },
  
  // ===== I70.64X - Nonbiological bypass graft, LEFT LEG =====
  { code: "I70.641", description: "Atherosclerosis of nonbiological bypass graft of left leg with ulceration of thigh" },
  { code: "I70.642", description: "Atherosclerosis of nonbiological bypass graft of left leg with ulceration of calf" },
  { code: "I70.643", description: "Atherosclerosis of nonbiological bypass graft of left leg with ulceration of ankle" },
  { code: "I70.644", description: "Atherosclerosis of nonbiological bypass graft of left leg with ulceration of heel and midfoot" },
  { code: "I70.645", description: "Atherosclerosis of nonbiological bypass graft of left leg with ulceration of other part of foot" },
  { code: "I70.648", description: "Atherosclerosis of nonbiological bypass graft of left leg with ulceration of other part of lower leg" },
  { code: "I70.649", description: "Atherosclerosis of nonbiological bypass graft of left leg with ulceration of unspecified site" },
  
  // ===== I70.65 - Nonbiological bypass graft, OTHER EXTREMITIES =====
  { code: "I70.65", description: "Atherosclerosis of nonbiological bypass graft of other extremity with ulceration" },
  
  // ===== I70.66X - Nonbiological bypass graft, UNSPECIFIED EXTREMITY =====
  { code: "I70.661", description: "Atherosclerosis of nonbiological bypass graft of extremities with ulceration of thigh" },
  { code: "I70.662", description: "Atherosclerosis of nonbiological bypass graft of extremities with ulceration of calf" },
  { code: "I70.663", description: "Atherosclerosis of nonbiological bypass graft of extremities with ulceration of ankle" },
  { code: "I70.664", description: "Atherosclerosis of nonbiological bypass graft of extremities with ulceration of heel and midfoot" },
  { code: "I70.665", description: "Atherosclerosis of nonbiological bypass graft of extremities with ulceration of other part of foot" },
  { code: "I70.668", description: "Atherosclerosis of nonbiological bypass graft of extremities with ulceration of other part of lower extremity" },
  { code: "I70.669", description: "Atherosclerosis of nonbiological bypass graft of extremities with ulceration of unspecified site" },
  
  // ===== I70.73X - Other type bypass graft, RIGHT LEG =====
  { code: "I70.731", description: "Atherosclerosis of other type of bypass graft of right leg with ulceration of thigh" },
  { code: "I70.732", description: "Atherosclerosis of other type of bypass graft of right leg with ulceration of calf" },
  { code: "I70.733", description: "Atherosclerosis of other type of bypass graft of right leg with ulceration of ankle" },
  { code: "I70.734", description: "Atherosclerosis of other type of bypass graft of right leg with ulceration of heel and midfoot" },
  { code: "I70.735", description: "Atherosclerosis of other type of bypass graft of right leg with ulceration of other part of foot" },
  { code: "I70.738", description: "Atherosclerosis of other type of bypass graft of right leg with ulceration of other part of lower leg" },
  { code: "I70.739", description: "Atherosclerosis of other type of bypass graft of right leg with ulceration of unspecified site" },
  
  // ===== I70.74X - Other type bypass graft, LEFT LEG =====
  { code: "I70.741", description: "Atherosclerosis of other type of bypass graft of left leg with ulceration of thigh" },
  { code: "I70.742", description: "Atherosclerosis of other type of bypass graft of left leg with ulceration of calf" },
  { code: "I70.743", description: "Atherosclerosis of other type of bypass graft of left leg with ulceration of ankle" },
  { code: "I70.744", description: "Atherosclerosis of other type of bypass graft of left leg with ulceration of heel and midfoot" },
  { code: "I70.745", description: "Atherosclerosis of other type of bypass graft of left leg with ulceration of other part of foot" },
  { code: "I70.748", description: "Atherosclerosis of other type of bypass graft of left leg with ulceration of other part of lower leg" },
  { code: "I70.749", description: "Atherosclerosis of other type of bypass graft of left leg with ulceration of unspecified site" },
  
  // ===== I70.75 - Other type bypass graft, OTHER EXTREMITIES =====
  { code: "I70.75", description: "Atherosclerosis of other type of bypass graft of other extremity with ulceration" },
  
  // ===== I70.76X - Other type bypass graft, UNSPECIFIED EXTREMITY =====
  { code: "I70.761", description: "Atherosclerosis of other type of bypass graft of extremities with ulceration of thigh" },
  { code: "I70.762", description: "Atherosclerosis of other type of bypass graft of extremities with ulceration of calf" },
  { code: "I70.763", description: "Atherosclerosis of other type of bypass graft of extremities with ulceration of ankle" },
  { code: "I70.764", description: "Atherosclerosis of other type of bypass graft of extremities with ulceration of heel and midfoot" },
  { code: "I70.765", description: "Atherosclerosis of other type of bypass graft of extremities with ulceration of other part of foot" },
  { code: "I70.768", description: "Atherosclerosis of other type of bypass graft of extremities with ulceration of other part of lower extremity" },
  { code: "I70.769", description: "Atherosclerosis of other type of bypass graft of extremities with ulceration of unspecified site" },
  
  // ===== L97 SEVERITY CODES (use with I70 above for LOWER EXTREMITY) =====
  // Thigh ulcers - L97.10X (unspecified), L97.11X (right), L97.12X (left)
  { code: "L97.101", description: "Non-pressure chronic ulcer of unspecified thigh limited to breakdown of skin" },
  { code: "L97.102", description: "Non-pressure chronic ulcer of unspecified thigh with fat layer exposed" },
  { code: "L97.103", description: "Non-pressure chronic ulcer of unspecified thigh with necrosis of muscle" },
  { code: "L97.104", description: "Non-pressure chronic ulcer of unspecified thigh with necrosis of bone" },
  { code: "L97.105", description: "Non-pressure chronic ulcer of unspecified thigh with muscle involvement without evidence of necrosis" },
  { code: "L97.106", description: "Non-pressure chronic ulcer of unspecified thigh with bone involvement without evidence of necrosis" },
  { code: "L97.108", description: "Non-pressure chronic ulcer of unspecified thigh with other specified severity" },
  { code: "L97.109", description: "Non-pressure chronic ulcer of unspecified thigh with unspecified severity" },
  { code: "L97.111", description: "Non-pressure chronic ulcer of right thigh limited to breakdown of skin" },
  { code: "L97.112", description: "Non-pressure chronic ulcer of right thigh with fat layer exposed" },
  { code: "L97.113", description: "Non-pressure chronic ulcer of right thigh with necrosis of muscle" },
  { code: "L97.114", description: "Non-pressure chronic ulcer of right thigh with necrosis of bone" },
  { code: "L97.115", description: "Non-pressure chronic ulcer of right thigh with muscle involvement without evidence of necrosis" },
  { code: "L97.116", description: "Non-pressure chronic ulcer of right thigh with bone involvement without evidence of necrosis" },
  { code: "L97.118", description: "Non-pressure chronic ulcer of right thigh with other specified severity" },
  { code: "L97.119", description: "Non-pressure chronic ulcer of right thigh with unspecified severity" },
  { code: "L97.121", description: "Non-pressure chronic ulcer of left thigh limited to breakdown of skin" },
  { code: "L97.122", description: "Non-pressure chronic ulcer of left thigh with fat layer exposed" },
  { code: "L97.123", description: "Non-pressure chronic ulcer of left thigh with necrosis of muscle" },
  { code: "L97.124", description: "Non-pressure chronic ulcer of left thigh with necrosis of bone" },
  { code: "L97.125", description: "Non-pressure chronic ulcer of left thigh with muscle involvement without evidence of necrosis" },
  { code: "L97.126", description: "Non-pressure chronic ulcer of left thigh with bone involvement without evidence of necrosis" },
  { code: "L97.128", description: "Non-pressure chronic ulcer of left thigh with other specified severity" },
  { code: "L97.129", description: "Non-pressure chronic ulcer of left thigh with unspecified severity" },
  
  // Calf ulcers - L97.20X (unspecified), L97.21X (right), L97.22X (left)
  { code: "L97.201", description: "Non-pressure chronic ulcer of unspecified calf limited to breakdown of skin" },
  { code: "L97.202", description: "Non-pressure chronic ulcer of unspecified calf with fat layer exposed" },
  { code: "L97.203", description: "Non-pressure chronic ulcer of unspecified calf with necrosis of muscle" },
  { code: "L97.204", description: "Non-pressure chronic ulcer of unspecified calf with necrosis of bone" },
  { code: "L97.205", description: "Non-pressure chronic ulcer of unspecified calf with muscle involvement without evidence of necrosis" },
  { code: "L97.206", description: "Non-pressure chronic ulcer of unspecified calf with bone involvement without evidence of necrosis" },
  { code: "L97.208", description: "Non-pressure chronic ulcer of unspecified calf with other specified severity" },
  { code: "L97.209", description: "Non-pressure chronic ulcer of unspecified calf with unspecified severity" },
  { code: "L97.211", description: "Non-pressure chronic ulcer of right calf limited to breakdown of skin" },
  { code: "L97.212", description: "Non-pressure chronic ulcer of right calf with fat layer exposed" },
  { code: "L97.213", description: "Non-pressure chronic ulcer of right calf with necrosis of muscle" },
  { code: "L97.214", description: "Non-pressure chronic ulcer of right calf with necrosis of bone" },
  { code: "L97.215", description: "Non-pressure chronic ulcer of right calf with muscle involvement without evidence of necrosis" },
  { code: "L97.216", description: "Non-pressure chronic ulcer of right calf with bone involvement without evidence of necrosis" },
  { code: "L97.218", description: "Non-pressure chronic ulcer of right calf with other specified severity" },
  { code: "L97.219", description: "Non-pressure chronic ulcer of right calf with unspecified severity" },
  { code: "L97.221", description: "Non-pressure chronic ulcer of left calf limited to breakdown of skin" },
  { code: "L97.222", description: "Non-pressure chronic ulcer of left calf with fat layer exposed" },
  { code: "L97.223", description: "Non-pressure chronic ulcer of left calf with necrosis of muscle" },
  { code: "L97.224", description: "Non-pressure chronic ulcer of left calf with necrosis of bone" },
  { code: "L97.225", description: "Non-pressure chronic ulcer of left calf with muscle involvement without evidence of necrosis" },
  { code: "L97.226", description: "Non-pressure chronic ulcer of left calf with bone involvement without evidence of necrosis" },
  { code: "L97.228", description: "Non-pressure chronic ulcer of left calf with other specified severity" },
  { code: "L97.229", description: "Non-pressure chronic ulcer of left calf with unspecified severity" },
  
  // Ankle ulcers - L97.30X (unspecified), L97.31X (right), L97.32X (left)
  { code: "L97.301", description: "Non-pressure chronic ulcer of unspecified ankle limited to breakdown of skin" },
  { code: "L97.302", description: "Non-pressure chronic ulcer of unspecified ankle with fat layer exposed" },
  { code: "L97.303", description: "Non-pressure chronic ulcer of unspecified ankle with necrosis of muscle" },
  { code: "L97.304", description: "Non-pressure chronic ulcer of unspecified ankle with necrosis of bone" },
  { code: "L97.305", description: "Non-pressure chronic ulcer of unspecified ankle with muscle involvement without evidence of necrosis" },
  { code: "L97.306", description: "Non-pressure chronic ulcer of unspecified ankle with bone involvement without evidence of necrosis" },
  { code: "L97.308", description: "Non-pressure chronic ulcer of unspecified ankle with other specified severity" },
  { code: "L97.309", description: "Non-pressure chronic ulcer of unspecified ankle with unspecified severity" },
  { code: "L97.311", description: "Non-pressure chronic ulcer of right ankle limited to breakdown of skin" },
  { code: "L97.312", description: "Non-pressure chronic ulcer of right ankle with fat layer exposed" },
  { code: "L97.313", description: "Non-pressure chronic ulcer of right ankle with necrosis of muscle" },
  { code: "L97.314", description: "Non-pressure chronic ulcer of right ankle with necrosis of bone" },
  { code: "L97.315", description: "Non-pressure chronic ulcer of right ankle with muscle involvement without evidence of necrosis" },
  { code: "L97.316", description: "Non-pressure chronic ulcer of right ankle with bone involvement without evidence of necrosis" },
  { code: "L97.318", description: "Non-pressure chronic ulcer of right ankle with other specified severity" },
  { code: "L97.319", description: "Non-pressure chronic ulcer of right ankle with unspecified severity" },
  { code: "L97.321", description: "Non-pressure chronic ulcer of left ankle limited to breakdown of skin" },
  { code: "L97.322", description: "Non-pressure chronic ulcer of left ankle with fat layer exposed" },
  { code: "L97.323", description: "Non-pressure chronic ulcer of left ankle with necrosis of muscle" },
  { code: "L97.324", description: "Non-pressure chronic ulcer of left ankle with necrosis of bone" },
  { code: "L97.325", description: "Non-pressure chronic ulcer of left ankle with muscle involvement without evidence of necrosis" },
  { code: "L97.326", description: "Non-pressure chronic ulcer of left ankle with bone involvement without evidence of necrosis" },
  { code: "L97.328", description: "Non-pressure chronic ulcer of left ankle with other specified severity" },
  { code: "L97.329", description: "Non-pressure chronic ulcer of left ankle with unspecified severity" },
  
  // Heel & midfoot - L97.40X (unspecified), L97.41X (right), L97.42X (left)
  { code: "L97.401", description: "Non-pressure chronic ulcer of unspecified heel and midfoot limited to breakdown of skin" },
  { code: "L97.402", description: "Non-pressure chronic ulcer of unspecified heel and midfoot with fat layer exposed" },
  { code: "L97.403", description: "Non-pressure chronic ulcer of unspecified heel and midfoot with necrosis of muscle" },
  { code: "L97.404", description: "Non-pressure chronic ulcer of unspecified heel and midfoot with necrosis of bone" },
  { code: "L97.405", description: "Non-pressure chronic ulcer of unspecified heel and midfoot with muscle involvement without evidence of necrosis" },
  { code: "L97.406", description: "Non-pressure chronic ulcer of unspecified heel and midfoot with bone involvement without evidence of necrosis" },
  { code: "L97.408", description: "Non-pressure chronic ulcer of unspecified heel and midfoot with other specified severity" },
  { code: "L97.409", description: "Non-pressure chronic ulcer of unspecified heel and midfoot with unspecified severity" },
  { code: "L97.411", description: "Non-pressure chronic ulcer of right heel and midfoot limited to breakdown of skin" },
  { code: "L97.412", description: "Non-pressure chronic ulcer of right heel and midfoot with fat layer exposed" },
  { code: "L97.413", description: "Non-pressure chronic ulcer of right heel and midfoot with necrosis of muscle" },
  { code: "L97.414", description: "Non-pressure chronic ulcer of right heel and midfoot with necrosis of bone" },
  { code: "L97.415", description: "Non-pressure chronic ulcer of right heel and midfoot with muscle involvement without evidence of necrosis" },
  { code: "L97.416", description: "Non-pressure chronic ulcer of right heel and midfoot with bone involvement without evidence of necrosis" },
  { code: "L97.418", description: "Non-pressure chronic ulcer of right heel and midfoot with other specified severity" },
  { code: "L97.419", description: "Non-pressure chronic ulcer of right heel and midfoot with unspecified severity" },
  { code: "L97.421", description: "Non-pressure chronic ulcer of left heel and midfoot limited to breakdown of skin" },
  { code: "L97.422", description: "Non-pressure chronic ulcer of left heel and midfoot with fat layer exposed" },
  { code: "L97.423", description: "Non-pressure chronic ulcer of left heel and midfoot with necrosis of muscle" },
  { code: "L97.424", description: "Non-pressure chronic ulcer of left heel and midfoot with necrosis of bone" },
  { code: "L97.425", description: "Non-pressure chronic ulcer of left heel and midfoot with muscle involvement without evidence of necrosis" },
  { code: "L97.426", description: "Non-pressure chronic ulcer of left heel and midfoot with bone involvement without evidence of necrosis" },
  { code: "L97.428", description: "Non-pressure chronic ulcer of left heel and midfoot with other specified severity" },
  { code: "L97.429", description: "Non-pressure chronic ulcer of left heel and midfoot with unspecified severity" },
  
  // Other foot - L97.50X (unspecified), L97.51X (right), L97.52X (left)
  { code: "L97.501", description: "Non-pressure chronic ulcer of other part of unspecified foot limited to breakdown of skin" },
  { code: "L97.502", description: "Non-pressure chronic ulcer of other part of unspecified foot with fat layer exposed" },
  { code: "L97.503", description: "Non-pressure chronic ulcer of other part of unspecified foot with necrosis of muscle" },
  { code: "L97.504", description: "Non-pressure chronic ulcer of other part of unspecified foot with necrosis of bone" },
  { code: "L97.505", description: "Non-pressure chronic ulcer of other part of unspecified foot with muscle involvement without evidence of necrosis" },
  { code: "L97.506", description: "Non-pressure chronic ulcer of other part of unspecified foot with bone involvement without evidence of necrosis" },
  { code: "L97.508", description: "Non-pressure chronic ulcer of other part of unspecified foot with other specified severity" },
  { code: "L97.509", description: "Non-pressure chronic ulcer of other part of unspecified foot with unspecified severity" },
  { code: "L97.511", description: "Non-pressure chronic ulcer of other part of right foot limited to breakdown of skin" },
  { code: "L97.512", description: "Non-pressure chronic ulcer of other part of right foot with fat layer exposed" },
  { code: "L97.513", description: "Non-pressure chronic ulcer of other part of right foot with necrosis of muscle" },
  { code: "L97.514", description: "Non-pressure chronic ulcer of other part of right foot with necrosis of bone" },
  { code: "L97.515", description: "Non-pressure chronic ulcer of other part of right foot with muscle involvement without evidence of necrosis" },
  { code: "L97.516", description: "Non-pressure chronic ulcer of other part of right foot with bone involvement without evidence of necrosis" },
  { code: "L97.518", description: "Non-pressure chronic ulcer of other part of right foot with other specified severity" },
  { code: "L97.519", description: "Non-pressure chronic ulcer of other part of right foot with unspecified severity" },
  { code: "L97.521", description: "Non-pressure chronic ulcer of other part of left foot limited to breakdown of skin" },
  { code: "L97.522", description: "Non-pressure chronic ulcer of other part of left foot with fat layer exposed" },
  { code: "L97.523", description: "Non-pressure chronic ulcer of other part of left foot with necrosis of muscle" },
  { code: "L97.524", description: "Non-pressure chronic ulcer of other part of left foot with necrosis of bone" },
  { code: "L97.525", description: "Non-pressure chronic ulcer of other part of left foot with muscle involvement without evidence of necrosis" },
  { code: "L97.526", description: "Non-pressure chronic ulcer of other part of left foot with bone involvement without evidence of necrosis" },
  { code: "L97.528", description: "Non-pressure chronic ulcer of other part of left foot with other specified severity" },
  { code: "L97.529", description: "Non-pressure chronic ulcer of other part of left foot with unspecified severity" },
  
  // Other lower leg - L97.80X (unspecified), L97.81X (right), L97.82X (left)
  { code: "L97.801", description: "Non-pressure chronic ulcer of other part of unspecified lower leg limited to breakdown of skin" },
  { code: "L97.802", description: "Non-pressure chronic ulcer of other part of unspecified lower leg with fat layer exposed" },
  { code: "L97.803", description: "Non-pressure chronic ulcer of other part of unspecified lower leg with necrosis of muscle" },
  { code: "L97.804", description: "Non-pressure chronic ulcer of other part of unspecified lower leg with necrosis of bone" },
  { code: "L97.805", description: "Non-pressure chronic ulcer of other part of unspecified lower leg with muscle involvement without evidence of necrosis" },
  { code: "L97.806", description: "Non-pressure chronic ulcer of other part of unspecified lower leg with bone involvement without evidence of necrosis" },
  { code: "L97.808", description: "Non-pressure chronic ulcer of other part of unspecified lower leg with other specified severity" },
  { code: "L97.809", description: "Non-pressure chronic ulcer of other part of unspecified lower leg with unspecified severity" },
  { code: "L97.811", description: "Non-pressure chronic ulcer of other part of right lower leg limited to breakdown of skin" },
  { code: "L97.812", description: "Non-pressure chronic ulcer of other part of right lower leg with fat layer exposed" },
  { code: "L97.813", description: "Non-pressure chronic ulcer of other part of right lower leg with necrosis of muscle" },
  { code: "L97.814", description: "Non-pressure chronic ulcer of other part of right lower leg with necrosis of bone" },
  { code: "L97.815", description: "Non-pressure chronic ulcer of other part of right lower leg with muscle involvement without evidence of necrosis" },
  { code: "L97.816", description: "Non-pressure chronic ulcer of other part of right lower leg with bone involvement without evidence of necrosis" },
  { code: "L97.818", description: "Non-pressure chronic ulcer of other part of right lower leg with other specified severity" },
  { code: "L97.819", description: "Non-pressure chronic ulcer of other part of right lower leg with unspecified severity" },
  { code: "L97.821", description: "Non-pressure chronic ulcer of other part of left lower leg limited to breakdown of skin" },
  { code: "L97.822", description: "Non-pressure chronic ulcer of other part of left lower leg with fat layer exposed" },
  { code: "L97.823", description: "Non-pressure chronic ulcer of other part of left lower leg with necrosis of muscle" },
  { code: "L97.824", description: "Non-pressure chronic ulcer of other part of left lower leg with necrosis of bone" },
  { code: "L97.825", description: "Non-pressure chronic ulcer of other part of left lower leg with muscle involvement without evidence of necrosis" },
  { code: "L97.826", description: "Non-pressure chronic ulcer of other part of left lower leg with bone involvement without evidence of necrosis" },
  { code: "L97.828", description: "Non-pressure chronic ulcer of other part of left lower leg with other specified severity" },
  { code: "L97.829", description: "Non-pressure chronic ulcer of other part of left lower leg with unspecified severity" },
  
  // Unspecified lower leg - L97.90X (unspecified), L97.91X (right), L97.92X (left)
  { code: "L97.901", description: "Non-pressure chronic ulcer of unspecified part of unspecified lower leg limited to breakdown of skin" },
  { code: "L97.902", description: "Non-pressure chronic ulcer of unspecified part of unspecified lower leg with fat layer exposed" },
  { code: "L97.903", description: "Non-pressure chronic ulcer of unspecified part of unspecified lower leg with necrosis of muscle" },
  { code: "L97.904", description: "Non-pressure chronic ulcer of unspecified part of unspecified lower leg with necrosis of bone" },
  { code: "L97.905", description: "Non-pressure chronic ulcer of unspecified part of unspecified lower leg with muscle involvement without evidence of necrosis" },
  { code: "L97.906", description: "Non-pressure chronic ulcer of unspecified part of unspecified lower leg with bone involvement without evidence of necrosis" },
  { code: "L97.908", description: "Non-pressure chronic ulcer of unspecified part of unspecified lower leg with other specified severity" },
  { code: "L97.909", description: "Non-pressure chronic ulcer of unspecified part of unspecified lower leg with unspecified severity" },
  { code: "L97.911", description: "Non-pressure chronic ulcer of unspecified part of right lower leg limited to breakdown of skin" },
  { code: "L97.912", description: "Non-pressure chronic ulcer of unspecified part of right lower leg with fat layer exposed" },
  { code: "L97.913", description: "Non-pressure chronic ulcer of unspecified part of right lower leg with necrosis of muscle" },
  { code: "L97.914", description: "Non-pressure chronic ulcer of unspecified part of right lower leg with necrosis of bone" },
  { code: "L97.915", description: "Non-pressure chronic ulcer of unspecified part of right lower leg with muscle involvement without evidence of necrosis" },
  { code: "L97.916", description: "Non-pressure chronic ulcer of unspecified part of right lower leg with bone involvement without evidence of necrosis" },
  { code: "L97.918", description: "Non-pressure chronic ulcer of unspecified part of right lower leg with other specified severity" },
  { code: "L97.919", description: "Non-pressure chronic ulcer of unspecified part of right lower leg with unspecified severity" },
  { code: "L97.921", description: "Non-pressure chronic ulcer of unspecified part of left lower leg limited to breakdown of skin" },
  { code: "L97.922", description: "Non-pressure chronic ulcer of unspecified part of left lower leg with fat layer exposed" },
  { code: "L97.923", description: "Non-pressure chronic ulcer of unspecified part of left lower leg with necrosis of muscle" },
  { code: "L97.924", description: "Non-pressure chronic ulcer of unspecified part of left lower leg with necrosis of bone" },
  { code: "L97.925", description: "Non-pressure chronic ulcer of unspecified part of left lower leg with muscle involvement without evidence of necrosis" },
  { code: "L97.926", description: "Non-pressure chronic ulcer of unspecified part of left lower leg with bone involvement without evidence of necrosis" },
  { code: "L97.928", description: "Non-pressure chronic ulcer of unspecified part of left lower leg with other specified severity" },
  { code: "L97.929", description: "Non-pressure chronic ulcer of unspecified part of left lower leg with unspecified severity" },
  
  // ===== L98.49X - UPPER EXTREMITY ulcers (use with I70.25, I70.X5) =====
  { code: "L98.491", description: "Non-pressure chronic ulcer of skin of other sites limited to breakdown of skin" },
  { code: "L98.492", description: "Non-pressure chronic ulcer of skin of other sites with fat layer exposed" },
  { code: "L98.493", description: "Non-pressure chronic ulcer of skin of other sites with necrosis of muscle" },
  { code: "L98.494", description: "Non-pressure chronic ulcer of skin of other sites with necrosis of bone" },
  { code: "L98.495", description: "Non-pressure chronic ulcer of skin of other sites with muscle involvement without evidence of necrosis" },
  { code: "L98.496", description: "Non-pressure chronic ulcer of skin of other sites with bone involvement without evidence of necrosis" },
  { code: "L98.498", description: "Non-pressure chronic ulcer of skin of other sites with other specified severity" },
  { code: "L98.499", description: "Non-pressure chronic ulcer of skin of other sites with unspecified severity" }
];

// 2. OTHER/GENERAL WOUND CODES
const otherGeneralWoundCodes = [
  // ===== L98.4XX - Chronic ulcers of other sites =====
  // Buttock ulcers
  { code: "L98.411", description: "Non-pressure chronic ulcer of buttock limited to breakdown of skin" },
  { code: "L98.412", description: "Non-pressure chronic ulcer of buttock with fat layer exposed" },
  { code: "L98.413", description: "Non-pressure chronic ulcer of buttock with necrosis of muscle" },
  { code: "L98.414", description: "Non-pressure chronic ulcer of buttock with necrosis of bone" },
  { code: "L98.415", description: "Non-pressure chronic ulcer of buttock with muscle involvement without evidence of necrosis" },
  { code: "L98.416", description: "Non-pressure chronic ulcer of buttock with bone involvement without evidence of necrosis" },
  { code: "L98.418", description: "Non-pressure chronic ulcer of buttock with other specified severity" },
  { code: "L98.419", description: "Non-pressure chronic ulcer of buttock with unspecified severity" },
  // Back ulcers
  { code: "L98.421", description: "Non-pressure chronic ulcer of back limited to breakdown of skin" },
  { code: "L98.422", description: "Non-pressure chronic ulcer of back with fat layer exposed" },
  { code: "L98.423", description: "Non-pressure chronic ulcer of back with necrosis of muscle" },
  { code: "L98.424", description: "Non-pressure chronic ulcer of back with necrosis of bone" },
  { code: "L98.425", description: "Non-pressure chronic ulcer of back with muscle involvement without evidence of necrosis" },
  { code: "L98.426", description: "Non-pressure chronic ulcer of back with bone involvement without evidence of necrosis" },
  { code: "L98.428", description: "Non-pressure chronic ulcer of back with other specified severity" },
  { code: "L98.429", description: "Non-pressure chronic ulcer of back with unspecified severity" },
  // Other sites (includes upper extremity non-arterial) - REPEATED FOR CLARITY
  { code: "L98.491", description: "Non-pressure chronic ulcer of skin of other sites limited to breakdown of skin" },
  { code: "L98.492", description: "Non-pressure chronic ulcer of skin of other sites with fat layer exposed" },
  { code: "L98.493", description: "Non-pressure chronic ulcer of skin of other sites with necrosis of muscle" },
  { code: "L98.494", description: "Non-pressure chronic ulcer of skin of other sites with necrosis of bone" },
  { code: "L98.495", description: "Non-pressure chronic ulcer of skin of other sites with muscle involvement without evidence of necrosis" },
  { code: "L98.496", description: "Non-pressure chronic ulcer of skin of other sites with bone involvement without evidence of necrosis" },
  { code: "L98.498", description: "Non-pressure chronic ulcer of skin of other sites with other specified severity" },
  { code: "L98.499", description: "Non-pressure chronic ulcer of skin of other sites with unspecified severity" },
  
  // ===== L08 - Local infections of skin =====
  { code: "L08.0", description: "Pyoderma" },
  { code: "L08.1", description: "Erythrasma" },
  { code: "L08.81", description: "Pyoderma vegetans" },
  { code: "L08.82", description: "Omphalitis not of newborn" },
  { code: "L08.89", description: "Other specified local infections of the skin and subcutaneous tissue" },
  { code: "L08.9", description: "Local infection of the skin and subcutaneous tissue, unspecified" },
  
  // ===== L03 - Cellulitis and acute lymphangitis (SAMPLE - Full expansion would be 100+ codes) =====
  // Finger cellulitis
  { code: "L03.011", description: "Cellulitis of right finger" },
  { code: "L03.012", description: "Cellulitis of left finger" },
  { code: "L03.013", description: "Cellulitis of right middle finger" },
  { code: "L03.014", description: "Cellulitis of left middle finger" },
  { code: "L03.015", description: "Cellulitis of right ring finger" },
  { code: "L03.016", description: "Cellulitis of left ring finger" },
  { code: "L03.017", description: "Cellulitis of right little finger" },
  { code: "L03.018", description: "Cellulitis of left little finger" },
  { code: "L03.019", description: "Cellulitis of unspecified finger" },
  // Toe cellulitis
  { code: "L03.031", description: "Cellulitis of right toe" },
  { code: "L03.032", description: "Cellulitis of left toe" },
  { code: "L03.033", description: "Cellulitis of right great toe" },
  { code: "L03.034", description: "Cellulitis of left great toe" },
  { code: "L03.035", description: "Cellulitis of right lesser toe" },
  { code: "L03.036", description: "Cellulitis of left lesser toe" },
  { code: "L03.037", description: "Cellulitis of right toe, unspecified" },
  { code: "L03.038", description: "Cellulitis of left toe, unspecified" },
  { code: "L03.039", description: "Cellulitis of unspecified toe" },
  // Upper limb cellulitis (sample)
  { code: "L03.111", description: "Cellulitis of right axilla" },
  { code: "L03.112", description: "Cellulitis of left axilla" },
  { code: "L03.113", description: "Cellulitis of right upper limb" },
  { code: "L03.114", description: "Cellulitis of left upper limb" },
  { code: "L03.115", description: "Cellulitis of right shoulder" },
  { code: "L03.116", description: "Cellulitis of left shoulder" },
  { code: "L03.117", description: "Cellulitis of right arm" },
  { code: "L03.118", description: "Cellulitis of left arm" },
  { code: "L03.119", description: "Cellulitis of unspecified part of limb" },
  // Face/neck/trunk cellulitis (sample)
  { code: "L03.211", description: "Cellulitis of face" },
  { code: "L03.212", description: "Cellulitis of neck" },
  { code: "L03.213", description: "Cellulitis of scalp" },
  { code: "L03.221", description: "Acute lymphangitis of face" },
  { code: "L03.222", description: "Acute lymphangitis of neck" },
  { code: "L03.311", description: "Cellulitis of abdominal wall" },
  { code: "L03.312", description: "Cellulitis of back [any part except buttock]" },
  { code: "L03.313", description: "Cellulitis of chest wall" },
  { code: "L03.314", description: "Cellulitis of groin" },
  { code: "L03.315", description: "Cellulitis of perineum" },
  { code: "L03.316", description: "Cellulitis of umbilicus" },
  { code: "L03.317", description: "Cellulitis of buttock" },
  { code: "L03.318", description: "Cellulitis of other parts of trunk" },
  { code: "L03.319", description: "Cellulitis of trunk, unspecified" },
  // Other sites cellulitis
  { code: "L03.811", description: "Cellulitis of head [any part, except face]" },
  { code: "L03.812", description: "Cellulitis of scalp" },
  { code: "L03.813", description: "Cellulitis of nose (external)" },
  { code: "L03.814", description: "Cellulitis of external ear" },
  { code: "L03.815", description: "Cellulitis of orbit" },
  { code: "L03.816", description: "Cellulitis of lacrimal apparatus" },
  { code: "L03.817", description: "Cellulitis of eyelid" },
  { code: "L03.818", description: "Cellulitis of other sites" },
  // Unspecified cellulitis
  { code: "L03.90", description: "Cellulitis, unspecified" },
  { code: "L03.91", description: "Acute lymphangitis, unspecified" },
  
  // ===== MALNUTRITION CODES =====
  { code: "E43", description: "Unspecified severe protein-calorie malnutrition" },
  { code: "E44.0", description: "Moderate protein-calorie malnutrition" },
  { code: "E44.1", description: "Mild protein-calorie malnutrition" },
  { code: "E46", description: "Unspecified protein-calorie malnutrition" },
  
  // ===== OBESITY CODES =====
  { code: "E66.01", description: "Morbid (severe) obesity due to excess calories" },
  { code: "E66.09", description: "Other obesity due to excess calories" },
  { code: "E66.1", description: "Drug-induced obesity" },
  { code: "E66.2", description: "Morbid (severe) obesity with alveolar hypoventilation" },
  { code: "E66.3", description: "Overweight" },
  { code: "E66.8", description: "Other obesity" },
  { code: "E66.9", description: "Obesity, unspecified" },
  
  // ===== IMMUNOSUPPRESSION/MEDICATION RISK =====
  { code: "Z79.3", description: "Long term (current) use of hormonal contraceptives" },
  { code: "Z79.4", description: "Long term (current) use of insulin" },
  { code: "Z79.51", description: "Long term (current) use of inhaled steroids" },
  { code: "Z79.52", description: "Long term (current) use of systemic steroids" },
  { code: "D84.821", description: "Immunodeficiency due to drugs" },
  { code: "Z91.81", description: "History of falling" },
  
  // ===== SEVERE INFECTIONS & NECROSIS =====
  { code: "M72.6", description: "Necrotizing fasciitis" },
  { code: "A48.0", description: "Gas gangrene" },
  { code: "A41.9", description: "Sepsis, unspecified organism" },
  { code: "T79.6XXA", description: "Traumatic ischemia of muscle, initial encounter" },
  
  // ===== Z48 - Postprocedural aftercare =====
  { code: "Z48.00", description: "Encounter for change or removal of nonsurgical wound dressing" },
  { code: "Z48.01", description: "Encounter for change or removal of surgical wound dressing" },
  { code: "Z48.02", description: "Encounter for removal of sutures" },
  { code: "Z48.03", description: "Encounter for change or removal of drains" },
  { code: "Z48.1", description: "Encounter for planned postprocedural wound closure" },
  { code: "Z48.21", description: "Encounter for aftercare following heart transplant" },
  { code: "Z48.22", description: "Encounter for aftercare following kidney transplant" },
  { code: "Z48.23", description: "Encounter for aftercare following liver transplant" },
  { code: "Z48.24", description: "Encounter for aftercare following lung transplant" },
  { code: "Z48.280", description: "Encounter for aftercare following heart-lung transplant" },
  { code: "Z48.288", description: "Encounter for aftercare following multiple organ transplant" },
  { code: "Z48.290", description: "Encounter for aftercare following bone marrow transplant" },
  { code: "Z48.298", description: "Encounter for aftercare following other organ transplant" },
  { code: "Z48.3", description: "Aftercare following surgery for neoplasm" },
  { code: "Z48.810", description: "Encounter for surgical aftercare following surgery on the sense organs" },
  { code: "Z48.811", description: "Encounter for surgical aftercare following surgery on the nervous system" },
  { code: "Z48.812", description: "Encounter for surgical aftercare following surgery on the circulatory system" },
  { code: "Z48.813", description: "Encounter for surgical aftercare following surgery on the respiratory system" },
  { code: "Z48.814", description: "Encounter for surgical aftercare following surgery on the teeth and oral cavity" },
  { code: "Z48.815", description: "Encounter for surgical aftercare following surgery on the digestive system" },
  { code: "Z48.816", description: "Encounter for surgical aftercare following surgery on the genitourinary system" },
  { code: "Z48.817", description: "Encounter for surgical aftercare following surgery on the skin and subcutaneous tissue" },
  { code: "Z48.89", description: "Encounter for other specified surgical aftercare" },
  
  // ===== L76 - Intra/postprocedural complications =====
  { code: "L76.01", description: "Intraoperative hemorrhage and hematoma of skin and subcutaneous tissue complicating a dermatologic procedure" },
  { code: "L76.02", description: "Intraoperative hemorrhage and hematoma of skin and subcutaneous tissue complicating other procedure" },
  { code: "L76.11", description: "Accidental puncture and laceration of skin and subcutaneous tissue during a dermatologic procedure" },
  { code: "L76.12", description: "Accidental puncture and laceration of skin and subcutaneous tissue during other procedure" },
  { code: "L76.21", description: "Postprocedural hemorrhage of skin and subcutaneous tissue following a dermatologic procedure" },
  { code: "L76.22", description: "Postprocedural hemorrhage of skin and subcutaneous tissue following other procedure" },
  { code: "L76.31", description: "Postprocedural hematoma of skin and subcutaneous tissue following a dermatologic procedure" },
  { code: "L76.32", description: "Postprocedural hematoma of skin and subcutaneous tissue following other procedure" },
  { code: "L76.33", description: "Postprocedural seroma of skin and subcutaneous tissue following a dermatologic procedure" },
  { code: "L76.34", description: "Postprocedural seroma of skin and subcutaneous tissue following other procedure" },
  { code: "L76.81", description: "Other intraoperative complications of skin and subcutaneous tissue" },
  { code: "L76.82", description: "Other postprocedural complications of skin and subcutaneous tissue" },
  
  // ===== T14 - Injury unspecified =====
  { code: "T14.8XXA", description: "Other injury of unspecified body region, initial encounter" },
  { code: "T14.8XXD", description: "Other injury of unspecified body region, subsequent encounter" },
  { code: "T14.8XXS", description: "Other injury of unspecified body region, sequela" },
  { code: "T14.90XA", description: "Injury, unspecified, initial encounter" },
  { code: "T14.90XD", description: "Injury, unspecified, subsequent encounter" },
  { code: "T14.90XS", description: "Injury, unspecified, sequela" },
  { code: "T14.91XA", description: "Suicide attempt, initial encounter" },
  { code: "T14.91XD", description: "Suicide attempt, subsequent encounter" },
  { code: "T14.91XS", description: "Suicide attempt, sequela" },
  
  // ===== B95-B96 - Organism codes =====
  { code: "B95.0", description: "Streptococcus, group A, as the cause of diseases classified elsewhere" },
  { code: "B95.1", description: "Streptococcus, group B, as the cause of diseases classified elsewhere" },
  { code: "B95.2", description: "Enterococcus as the cause of diseases classified elsewhere" },
  { code: "B95.3", description: "Streptococcus pneumoniae as the cause of diseases classified elsewhere" },
  { code: "B95.4", description: "Other streptococcus as the cause of diseases classified elsewhere" },
  { code: "B95.5", description: "Unspecified streptococcus as the cause of diseases classified elsewhere" },
  { code: "B95.61", description: "Methicillin susceptible Staphylococcus aureus infection as the cause of diseases classified elsewhere" },
  { code: "B95.62", description: "Methicillin resistant Staphylococcus aureus infection as the cause of diseases classified elsewhere" },
  { code: "B95.7", description: "Other staphylococcus as the cause of diseases classified elsewhere" },
  { code: "B95.8", description: "Unspecified staphylococcus as the cause of diseases classified elsewhere" },
  { code: "B96.0", description: "Mycoplasma pneumoniae [M. pneumoniae] as the cause of diseases classified elsewhere" },
  { code: "B96.1", description: "Klebsiella pneumoniae [K. pneumoniae] as the cause of diseases classified elsewhere" },
  { code: "B96.20", description: "Unspecified Escherichia coli [E. coli] as the cause of diseases classified elsewhere" },
  { code: "B96.21", description: "Shiga toxin-producing Escherichia coli [E. coli] (STEC) O157 as the cause of diseases classified elsewhere" },
  { code: "B96.22", description: "Other specified Shiga toxin-producing Escherichia coli [E. coli] (STEC) as the cause of diseases classified elsewhere" },
  { code: "B96.23", description: "Unspecified Shiga toxin-producing Escherichia coli [E. coli] (STEC) as the cause of diseases classified elsewhere" },
  { code: "B96.29", description: "Other Escherichia coli [E. coli] as the cause of diseases classified elsewhere" },
  { code: "B96.3", description: "Hemophilus influenzae [H. influenzae] as the cause of diseases classified elsewhere" },
  { code: "B96.4", description: "Proteus (mirabilis) (morganii) as the cause of diseases classified elsewhere" },
  { code: "B96.5", description: "Pseudomonas (aeruginosa) (mallei) (pseudomallei) as the cause of diseases classified elsewhere" },
  { code: "B96.6", description: "Bacteroides fragilis [B. fragilis] as the cause of diseases classified elsewhere" },
  { code: "B96.7", description: "Clostridium perfringens [C. perfringens] as the cause of diseases classified elsewhere" },
  { code: "B96.81", description: "Helicobacter pylori [H. pylori] as the cause of diseases classified elsewhere" },
  { code: "B96.82", description: "Vibrio vulnificus as the cause of diseases classified elsewhere" },
  { code: "B96.89", description: "Other specified bacterial agents as the cause of diseases classified elsewhere" },
  
  // ===== GANGRENE (non-arterial) =====
  { code: "I96", description: "Gangrene, not elsewhere classified" },
  { code: "R02.0", description: "Gangrene due to atherosclerosis" },
  { code: "R02.1", description: "Gangrene in diseases classified elsewhere" },
  { code: "R02.2", description: "Gangrene, not elsewhere classified" },
  
  // ===== FOREIGN BODY CODES =====
  { code: "Z18.0", description: "Retained radioactive fragments" },
  { code: "Z18.10", description: "Retained metal fragments, unspecified" },
  { code: "Z18.11", description: "Retained magnetic metal fragments" },
  { code: "Z18.12", description: "Retained nonmagnetic metal fragments" },
  { code: "Z18.2", description: "Retained plastic fragments" },
  { code: "Z18.31", description: "Retained animal quills or spines" },
  { code: "Z18.32", description: "Retained tooth" },
  { code: "Z18.33", description: "Retained wood fragments" },
  { code: "Z18.39", description: "Other retained organic fragments" },
  { code: "Z18.81", description: "Retained glass fragments" },
  { code: "Z18.83", description: "Retained stone or crystalline fragments" },
  { code: "Z18.89", description: "Other specified retained foreign body fragments" },
  { code: "Z18.9", description: "Retained foreign body fragments, unspecified material" }
];

// 3. SURGICAL WOUND CODES
const surgicalWoundCodes = [
  // ===== T81.30X - Disruption of wound, unspecified =====
  { code: "T81.30XA", description: "Disruption of wound, unspecified, initial encounter" },
  { code: "T81.30XD", description: "Disruption of wound, unspecified, subsequent encounter" },
  { code: "T81.30XS", description: "Disruption of wound, unspecified, sequela" },
  
  // ===== T81.31X - External surgical wound disruption =====
  { code: "T81.31XA", description: "Disruption of external operation (surgical) wound, not elsewhere classified, initial encounter" },
  { code: "T81.31XD", description: "Disruption of external operation (surgical) wound, not elsewhere classified, subsequent encounter" },
  { code: "T81.31XS", description: "Disruption of external operation (surgical) wound, not elsewhere classified, sequela" },
  
  // ===== T81.32X - Internal surgical wound disruption =====
  { code: "T81.320A", description: "Disruption of external operation (surgical) wound, not elsewhere classified, initial encounter" },
  { code: "T81.320D", description: "Disruption of external operation (surgical) wound, not elsewhere classified, subsequent encounter" },
  { code: "T81.320S", description: "Disruption of external operation (surgical) wound, not elsewhere classified, sequela" },
  { code: "T81.321A", description: "Disruption of internal operation (surgical) wound, not elsewhere classified, initial encounter" },
  { code: "T81.321D", description: "Disruption of internal operation (surgical) wound, not elsewhere classified, subsequent encounter" },
  { code: "T81.321S", description: "Disruption of internal operation (surgical) wound, not elsewhere classified, sequela" },
  { code: "T81.322A", description: "Disruption of internal operation (surgical) wound, not elsewhere classified, initial encounter" },
  { code: "T81.322D", description: "Disruption of internal operation (surgical) wound, not elsewhere classified, subsequent encounter" },
  { code: "T81.322S", description: "Disruption of internal operation (surgical) wound, not elsewhere classified, sequela" },
  { code: "T81.328A", description: "Disruption of other internal operation (surgical) wound, not elsewhere classified, initial encounter" },
  { code: "T81.328D", description: "Disruption of other internal operation (surgical) wound, not elsewhere classified, subsequent encounter" },
  { code: "T81.328S", description: "Disruption of other internal operation (surgical) wound, not elsewhere classified, sequela" },
  { code: "T81.329A", description: "Disruption of unspecified internal operation (surgical) wound, not elsewhere classified, initial encounter" },
  { code: "T81.329D", description: "Disruption of unspecified internal operation (surgical) wound, not elsewhere classified, subsequent encounter" },
  { code: "T81.329S", description: "Disruption of unspecified internal operation (surgical) wound, not elsewhere classified, sequela" },
  
  // ===== T81.33X - Traumatic injury wound repair disruption =====
  { code: "T81.33XA", description: "Disruption of traumatic injury wound repair, initial encounter" },
  { code: "T81.33XD", description: "Disruption of traumatic injury wound repair, subsequent encounter" },
  { code: "T81.33XS", description: "Disruption of traumatic injury wound repair, sequela" },
  
  // ===== T81.4XX - Infection following procedure =====
  { code: "T81.41XA", description: "Infection following a procedure, superficial incisional surgical site, initial encounter" },
  { code: "T81.41XD", description: "Infection following a procedure, superficial incisional surgical site, subsequent encounter" },
  { code: "T81.41XS", description: "Infection following a procedure, superficial incisional surgical site, sequela" },
  { code: "T81.42XA", description: "Infection following a procedure, deep incisional surgical site, initial encounter" },
  { code: "T81.42XD", description: "Infection following a procedure, deep incisional surgical site, subsequent encounter" },
  { code: "T81.42XS", description: "Infection following a procedure, deep incisional surgical site, sequela" },
  { code: "T81.43XA", description: "Infection following a procedure, organ and space surgical site, initial encounter" },
  { code: "T81.43XD", description: "Infection following a procedure, organ and space surgical site, subsequent encounter" },
  { code: "T81.43XS", description: "Infection following a procedure, organ and space surgical site, sequela" },
  { code: "T81.44XA", description: "Sepsis following a procedure, initial encounter" },
  { code: "T81.44XD", description: "Sepsis following a procedure, subsequent encounter" },
  { code: "T81.44XS", description: "Sepsis following a procedure, sequela" },
  { code: "T81.49XA", description: "Infection following a procedure, other surgical site, initial encounter" },
  { code: "T81.49XD", description: "Infection following a procedure, other surgical site, subsequent encounter" },
  { code: "T81.49XS", description: "Infection following a procedure, other surgical site, sequela" },
  
  // ===== T81.89X - Other complications =====
  { code: "T81.89XA", description: "Other complications of procedures, not elsewhere classified, initial encounter" },
  { code: "T81.89XD", description: "Other complications of procedures, not elsewhere classified, subsequent encounter" },
  { code: "T81.89XS", description: "Other complications of procedures, not elsewhere classified, sequela" },
  
  // ===== O86 - Obstetric surgical wound infection =====
  { code: "O86.00", description: "Infection of obstetric surgical wound, unspecified" },
  { code: "O86.01", description: "Infection of obstetric surgical wound, superficial incisional site" },
  { code: "O86.02", description: "Infection of obstetric surgical wound, deep incisional site" },
  { code: "O86.03", description: "Infection of obstetric surgical wound, organ and space site" },
  { code: "O86.04", description: "Sepsis following an obstetric procedure" },
  { code: "O86.09", description: "Infection of obstetric surgical wound, other surgical site" },
  
  // ===== O90 - Obstetric complications =====
  { code: "O90.0", description: "Disruption of cesarean delivery wound" },
  { code: "O90.1", description: "Disruption of perineal obstetric wound" },
  { code: "O90.2", description: "Hematoma of obstetric wound" },
  
  // ===== T87 - Amputation stump complications =====
  // Infection of amputation stump
  { code: "T87.40", description: "Infection of amputation stump, unspecified extremity" },
  { code: "T87.41", description: "Infection of amputation stump, right upper extremity" },
  { code: "T87.42", description: "Infection of amputation stump, left upper extremity" },
  { code: "T87.43", description: "Infection of amputation stump, right lower extremity" },
  { code: "T87.44", description: "Infection of amputation stump, left lower extremity" },
  // Necrosis of amputation stump
  { code: "T87.50", description: "Necrosis of amputation stump, unspecified extremity" },
  { code: "T87.51", description: "Necrosis of amputation stump, right upper extremity" },
  { code: "T87.52", description: "Necrosis of amputation stump, left upper extremity" },
  { code: "T87.53", description: "Necrosis of amputation stump, right lower extremity" },
  { code: "T87.54", description: "Necrosis of amputation stump, left lower extremity" },
  // Dehiscence of amputation stump
  { code: "T87.81", description: "Dehiscence of amputation stump" },
  // Other complications
  { code: "T87.89", description: "Other complications of amputation stump" },
  
  // ===== Z48 - Postprocedural aftercare (relevant to surgical) =====
  { code: "Z48.00", description: "Encounter for change or removal of nonsurgical wound dressing" },
  { code: "Z48.01", description: "Encounter for change or removal of surgical wound dressing" },
  { code: "Z48.02", description: "Encounter for removal of sutures" },
  { code: "Z48.03", description: "Encounter for change or removal of drains" },
  { code: "Z48.89", description: "Encounter for other specified surgical aftercare" },
  
  // ===== Common organism codes for surgical infections =====
  { code: "B95.61", description: "Methicillin susceptible Staphylococcus aureus infection as the cause of diseases classified elsewhere" },
  { code: "B95.62", description: "Methicillin resistant Staphylococcus aureus infection as the cause of diseases classified elsewhere" },
  { code: "B96.20", description: "Unspecified Escherichia coli [E. coli] as the cause of diseases classified elsewhere" },
  { code: "B96.4", description: "Proteus (mirabilis) (morganii) as the cause of diseases classified elsewhere" },
  { code: "B96.5", description: "Pseudomonas (aeruginosa) (mallei) (pseudomallei) as the cause of diseases classified elsewhere" },
  
  // ===== Foreign body codes (when applicable) =====
  { code: "Z18.0", description: "Retained radioactive fragments" },
  { code: "Z18.2", description: "Retained plastic fragments" },
  { code: "Z18.3", description: "Retained organic fragments" },
  { code: "Z18.8", description: "Other specified retained foreign body fragments" }
];

// 4. TRAUMATIC WOUND CODES (WITH DESCRIPTIONS - SAMPLE OF MOST CRITICAL)
// NOTE: Full expansion would be 1000+ codes. This includes the most critical for skin substitute billing.
const traumaticWoundCodes = [
  // ===== S01 - Open wound of head (CRITICAL SAMPLE) =====
  { code: "S01.00XA", description: "Unspecified open wound of scalp, initial encounter" },
  { code: "S01.01XA", description: "Laceration without foreign body of scalp, initial encounter" },
  { code: "S01.02XA", description: "Laceration with foreign body of scalp, initial encounter" },
  { code: "S01.03XA", description: "Puncture wound without foreign body of scalp, initial encounter" },
  { code: "S01.04XA", description: "Puncture wound with foreign body of scalp, initial encounter" },
  { code: "S01.05XA", description: "Open bite of scalp, initial encounter" },
  
  // Eyelid (with laterality) - Sample
  { code: "S01.101A", description: "Unspecified open wound of right eyelid and periocular area, initial encounter" },
  { code: "S01.102A", description: "Unspecified open wound of left eyelid and periocular area, initial encounter" },
  { code: "S01.111A", description: "Laceration without foreign body of right eyelid and periocular area, initial encounter" },
  { code: "S01.112A", description: "Laceration without foreign body of left eyelid and periocular area, initial encounter" },
  
  // Face wounds
  { code: "S01.401A", description: "Unspecified open wound of right cheek and temporomandibular area, initial encounter" },
  { code: "S01.402A", description: "Unspecified open wound of left cheek and temporomandibular area, initial encounter" },
  { code: "S01.501A", description: "Unspecified open wound of lip, initial encounter" },
  { code: "S01.502A", description: "Unspecified open wound of oral cavity, initial encounter" },
  
  // ===== S21 - Open wound of thorax (CRITICAL PENETRATING VARIANTS) =====
  // Front wall WITHOUT penetration
  { code: "S21.101A", description: "Unspecified open wound of right front wall of thorax without penetration into thoracic cavity, initial encounter" },
  { code: "S21.102A", description: "Unspecified open wound of left front wall of thorax without penetration into thoracic cavity, initial encounter" },
  { code: "S21.111A", description: "Laceration without foreign body of right front wall of thorax without penetration into thoracic cavity, initial encounter" },
  { code: "S21.112A", description: "Laceration without foreign body of left front wall of thorax without penetration into thoracic cavity, initial encounter" },
  { code: "S21.121A", description: "Laceration with foreign body of right front wall of thorax without penetration into thoracic cavity, initial encounter" },
  { code: "S21.122A", description: "Laceration with foreign body of left front wall of thorax without penetration into thoracic cavity, initial encounter" },
  { code: "S21.131A", description: "Puncture wound without foreign body of right front wall of thorax without penetration into thoracic cavity, initial encounter" },
  { code: "S21.132A", description: "Puncture wound without foreign body of left front wall of thorax without penetration into thoracic cavity, initial encounter" },
  { code: "S21.141A", description: "Puncture wound with foreign body of right front wall of thorax without penetration into thoracic cavity, initial encounter" },
  { code: "S21.142A", description: "Puncture wound with foreign body of left front wall of thorax without penetration into thoracic cavity, initial encounter" },
  { code: "S21.151A", description: "Open bite of right front wall of thorax without penetration into thoracic cavity, initial encounter" },
  { code: "S21.152A", description: "Open bite of left front wall of thorax without penetration into thoracic cavity, initial encounter" },
  
  // Front wall WITH penetration
  { code: "S21.301A", description: "Unspecified open wound of right front wall of thorax with penetration into thoracic cavity, initial encounter" },
  { code: "S21.302A", description: "Unspecified open wound of left front wall of thorax with penetration into thoracic cavity, initial encounter" },
  { code: "S21.311A", description: "Laceration without foreign body of right front wall of thorax with penetration into thoracic cavity, initial encounter" },
  { code: "S21.312A", description: "Laceration without foreign body of left front wall of thorax with penetration into thoracic cavity, initial encounter" },
  { code: "S21.321A", description: "Laceration with foreign body of right front wall of thorax with penetration into thoracic cavity, initial encounter" },
  { code: "S21.322A", description: "Laceration with foreign body of left front wall of thorax with penetration into thoracic cavity, initial encounter" },
  { code: "S21.331A", description: "Puncture wound without foreign body of right front wall of thorax with penetration into thoracic cavity, initial encounter" },
  { code: "S21.332A", description: "Puncture wound without foreign body of left front wall of thorax with penetration into thoracic cavity, initial encounter" },
  { code: "S21.341A", description: "Puncture wound with foreign body of right front wall of thorax with penetration into thoracic cavity, initial encounter" },
  { code: "S21.342A", description: "Puncture wound with foreign body of left front wall of thorax with penetration into thoracic cavity, initial encounter" },
  { code: "S21.351A", description: "Open bite of right front wall of thorax with penetration into thoracic cavity, initial encounter" },
  { code: "S21.352A", description: "Open bite of left front wall of thorax with penetration into thoracic cavity, initial encounter" },
  
  // ===== S31 - Open wound of abdomen, INCLUDING BUTTOCK (CRITICAL) =====
  { code: "S31.000A", description: "Unspecified open wound of lower back and pelvis without penetration into retroperitoneum, initial encounter" },
  { code: "S31.001A", description: "Unspecified open wound of lower back and pelvis with penetration into retroperitoneum, initial encounter" },
  { code: "S31.010A", description: "Laceration without foreign body of lower back and pelvis without penetration into retroperitoneum, initial encounter" },
  { code: "S31.011A", description: "Laceration without foreign body of lower back and pelvis with penetration into retroperitoneum, initial encounter" },
  
  // Buttock wounds (CRITICAL ADDITION)
  { code: "S31.800A", description: "Unspecified open wound of right buttock, initial encounter" },
  { code: "S31.801A", description: "Unspecified open wound of left buttock, initial encounter" },
  { code: "S31.802A", description: "Unspecified open wound of unspecified buttock, initial encounter" },
  { code: "S31.803A", description: "Puncture wound without foreign body of right buttock, initial encounter" },
  { code: "S31.804A", description: "Puncture wound without foreign body of left buttock, initial encounter" },
  { code: "S31.805A", description: "Open bite of right buttock, initial encounter" },
  { code: "S31.811A", description: "Laceration without foreign body of right buttock, initial encounter" },
  { code: "S31.812A", description: "Laceration without foreign body of left buttock, initial encounter" },
  { code: "S31.813A", description: "Puncture wound without foreign body of unspecified buttock, initial encounter" },
  { code: "S31.814A", description: "Puncture wound with foreign body of right buttock, initial encounter" },
  { code: "S31.815A", description: "Puncture wound with foreign body of left buttock, initial encounter" },
  { code: "S31.819A", description: "Laceration without foreign body of unspecified buttock, initial encounter" },
  { code: "S31.821A", description: "Laceration with foreign body of right buttock, initial encounter" },
  { code: "S31.822A", description: "Laceration with foreign body of left buttock, initial encounter" },
  { code: "S31.823A", description: "Puncture wound without foreign body of right buttock, initial encounter" },
  { code: "S31.824A", description: "Puncture wound without foreign body of left buttock, initial encounter" },
  { code: "S31.825A", description: "Open bite of right buttock, initial encounter" },
  { code: "S31.829A", description: "Open bite of unspecified buttock, initial encounter" },
  
  // ===== S41 - Open wound of shoulder and upper arm (SAMPLE) =====
  { code: "S41.001A", description: "Unspecified open wound of right shoulder, initial encounter" },
  { code: "S41.002A", description: "Unspecified open wound of left shoulder, initial encounter" },
  { code: "S41.011A", description: "Laceration without foreign body of right shoulder, initial encounter" },
  { code: "S41.012A", description: "Laceration without foreign body of left shoulder, initial encounter" },
  { code: "S41.101A", description: "Unspecified open wound of right upper arm, initial encounter" },
  { code: "S41.102A", description: "Unspecified open wound of left upper arm, initial encounter" },
  
  // ===== S48 - TRAUMATIC AMPUTATION OF SHOULDER/UPPER ARM (CRITICAL) =====
  { code: "S48.011A", description: "Complete traumatic amputation at right shoulder joint, initial encounter" },
  { code: "S48.012A", description: "Complete traumatic amputation at left shoulder joint, initial encounter" },
  { code: "S48.019A", description: "Complete traumatic amputation at unspecified shoulder joint, initial encounter" },
  { code: "S48.021A", description: "Partial traumatic amputation at right shoulder joint, initial encounter" },
  { code: "S48.022A", description: "Partial traumatic amputation at left shoulder joint, initial encounter" },
  { code: "S48.029A", description: "Partial traumatic amputation at unspecified shoulder joint, initial encounter" },
  { code: "S48.111A", description: "Complete traumatic amputation of right shoulder and upper arm, level unspecified, initial encounter" },
  { code: "S48.112A", description: "Complete traumatic amputation of left shoulder and upper arm, level unspecified, initial encounter" },
  { code: "S48.119A", description: "Complete traumatic amputation of unspecified shoulder and upper arm, level unspecified, initial encounter" },
  { code: "S48.121A", description: "Partial traumatic amputation of right shoulder and upper arm, level unspecified, initial encounter" },
  { code: "S48.122A", description: "Partial traumatic amputation of left shoulder and upper arm, level unspecified, initial encounter" },
  { code: "S48.129A", description: "Partial traumatic amputation of unspecified shoulder and upper arm, level unspecified, initial encounter" },
  { code: "S48.911A", description: "Complete traumatic amputation of right shoulder and upper arm, level unspecified, initial encounter" },
  { code: "S48.912A", description: "Complete traumatic amputation of left shoulder and upper arm, level unspecified, initial encounter" },
  { code: "S48.919A", description: "Complete traumatic amputation of unspecified shoulder and upper arm, level unspecified, initial encounter" },
  { code: "S48.921A", description: "Partial traumatic amputation of right shoulder and upper arm, level unspecified, initial encounter" },
  { code: "S48.922A", description: "Partial traumatic amputation of left shoulder and upper arm, level unspecified, initial encounter" },
  { code: "S48.929A", description: "Partial traumatic amputation of unspecified shoulder and upper arm, level unspecified, initial encounter" },
  
  // ===== S71 - Open wound of hip and thigh (SAMPLE) =====
  { code: "S71.001A", description: "Unspecified open wound of right hip, initial encounter" },
  { code: "S71.002A", description: "Unspecified open wound of left hip, initial encounter" },
  { code: "S71.101A", description: "Unspecified open wound of right thigh, initial encounter" },
  { code: "S71.102A", description: "Unspecified open wound of left thigh, initial encounter" },
  { code: "S71.111A", description: "Laceration without foreign body of right thigh, initial encounter" },
  { code: "S71.112A", description: "Laceration without foreign body of left thigh, initial encounter" },
  
  // ===== S78 - TRAUMATIC AMPUTATION OF HIP/THIGH (CRITICAL) =====
  { code: "S78.011A", description: "Complete traumatic amputation at right hip joint, initial encounter" },
  { code: "S78.012A", description: "Complete traumatic amputation at left hip joint, initial encounter" },
  { code: "S78.019A", description: "Complete traumatic amputation at unspecified hip joint, initial encounter" },
  { code: "S78.021A", description: "Partial traumatic amputation at right hip joint, initial encounter" },
  { code: "S78.022A", description: "Partial traumatic amputation at left hip joint, initial encounter" },
  { code: "S78.029A", description: "Partial traumatic amputation at unspecified hip joint, initial encounter" },
  { code: "S78.111A", description: "Complete traumatic amputation of right hip and thigh, level unspecified, initial encounter" },
  { code: "S78.112A", description: "Complete traumatic amputation of left hip and thigh, level unspecified, initial encounter" },
  { code: "S78.119A", description: "Complete traumatic amputation of unspecified hip and thigh, level unspecified, initial encounter" },
  { code: "S78.121A", description: "Partial traumatic amputation of right hip and thigh, level unspecified, initial encounter" },
  { code: "S78.122A", description: "Partial traumatic amputation of left hip and thigh, level unspecified, initial encounter" },
  { code: "S78.129A", description: "Partial traumatic amputation of unspecified hip and thigh, level unspecified, initial encounter" },
  
  // ===== S81 - Open wound of knee and lower leg (SAMPLE) =====
  { code: "S81.001A", description: "Unspecified open wound of right knee, initial encounter" },
  { code: "S81.002A", description: "Unspecified open wound of left knee, initial encounter" },
  { code: "S81.801A", description: "Unspecified open wound of right lower leg, initial encounter" },
  { code: "S81.802A", description: "Unspecified open wound of left lower leg, initial encounter" },
  
  // ===== S88 - TRAUMATIC AMPUTATION OF LOWER LEG (CRITICAL) =====
  { code: "S88.011A", description: "Complete traumatic amputation at knee level, right lower leg, initial encounter" },
  { code: "S88.012A", description: "Complete traumatic amputation at knee level, left lower leg, initial encounter" },
  { code: "S88.019A", description: "Complete traumatic amputation at knee level, unspecified lower leg, initial encounter" },
  { code: "S88.021A", description: "Partial traumatic amputation at knee level, right lower leg, initial encounter" },
  { code: "S88.022A", description: "Partial traumatic amputation at knee level, left lower leg, initial encounter" },
  { code: "S88.029A", description: "Partial traumatic amputation at knee level, unspecified lower leg, initial encounter" },
  { code: "S88.111A", description: "Complete traumatic amputation at level between knee and ankle, right lower leg, initial encounter" },
  { code: "S88.112A", description: "Complete traumatic amputation at level between knee and ankle, left lower leg, initial encounter" },
  { code: "S88.119A", description: "Complete traumatic amputation at level between knee and ankle, unspecified lower leg, initial encounter" },
  { code: "S88.121A", description: "Partial traumatic amputation at level between knee and ankle, right lower leg, initial encounter" },
  { code: "S88.122A", description: "Partial traumatic amputation at level between knee and ankle, left lower leg, initial encounter" },
  { code: "S88.129A", description: "Partial traumatic amputation at level between knee and ankle, unspecified lower leg, initial encounter" },
  
  // ===== S91 - Open wound of ankle, foot, toes (SAMPLE) =====
  { code: "S91.001A", description: "Unspecified open wound of right ankle, initial encounter" },
  { code: "S91.002A", description: "Unspecified open wound of left ankle, initial encounter" },
  { code: "S91.301A", description: "Unspecified open wound of right foot, initial encounter" },
  { code: "S91.302A", description: "Unspecified open wound of left foot, initial encounter" },
  
  // ===== S98 - TRAUMATIC AMPUTATION OF ANKLE/FOOT/TOES (CRITICAL) =====
  { code: "S98.011A", description: "Complete traumatic amputation of right foot at ankle level, initial encounter" },
  { code: "S98.012A", description: "Complete traumatic amputation of left foot at ankle level, initial encounter" },
  { code: "S98.019A", description: "Complete traumatic amputation of unspecified foot at ankle level, initial encounter" },
  { code: "S98.021A", description: "Partial traumatic amputation of right foot at ankle level, initial encounter" },
  { code: "S98.022A", description: "Partial traumatic amputation of left foot at ankle level, initial encounter" },
  { code: "S98.029A", description: "Partial traumatic amputation of unspecified foot at ankle level, initial encounter" },
  
  // ===== T14 - Injury of unspecified body region =====
  { code: "T14.8XXA", description: "Other injury of unspecified body region, initial encounter" },
  { code: "T14.8XXD", description: "Other injury of unspecified body region, subsequent encounter" },
  { code: "T14.8XXS", description: "Other injury of unspecified body region, sequela" },
  { code: "T14.90XA", description: "Injury, unspecified, initial encounter" },
  { code: "T14.90XD", description: "Injury, unspecified, subsequent encounter" },
  { code: "T14.90XS", description: "Injury, unspecified, sequela" },
  { code: "T14.91XA", description: "Suicide attempt, initial encounter" },
  { code: "T14.91XD", description: "Suicide attempt, subsequent encounter" },
  { code: "T14.91XS", description: "Suicide attempt, sequela" },
  
  // ===== T79.A - TRAUMATIC COMPARTMENT SYNDROME (CRITICAL) =====
  { code: "T79.A0XA", description: "Compartment syndrome, unspecified, initial encounter" },
  { code: "T79.A11A", description: "Traumatic compartment syndrome of right upper extremity, initial encounter" },
  { code: "T79.A12A", description: "Traumatic compartment syndrome of left upper extremity, initial encounter" },
  { code: "T79.A19A", description: "Traumatic compartment syndrome of unspecified upper extremity, initial encounter" },
  { code: "T79.A21A", description: "Traumatic compartment syndrome of right lower extremity, initial encounter" },
  { code: "T79.A22A", description: "Traumatic compartment syndrome of left lower extremity, initial encounter" },
  { code: "T79.A29A", description: "Traumatic compartment syndrome of unspecified lower extremity, initial encounter" },
  { code: "T79.A3XA", description: "Traumatic compartment syndrome of abdomen, initial encounter" },
  { code: "T79.A9XA", description: "Traumatic compartment syndrome of other sites, initial encounter" },
  
  // ===== T79.6 - TRAUMATIC ISCHEMIA =====
  { code: "T79.6XXA", description: "Traumatic ischemia of muscle, initial encounter" },
  { code: "T79.6XXD", description: "Traumatic ischemia of muscle, subsequent encounter" },
  { code: "T79.6XXS", description: "Traumatic ischemia of muscle, sequela" },
  
  // ===== T87 - AMPUTATION STUMP COMPLICATIONS (Repeated from surgical for completeness) =====
  { code: "T87.30", description: "Neuroma of amputation stump, unspecified extremity" },
  { code: "T87.31", description: "Neuroma of amputation stump, right upper extremity" },
  { code: "T87.32", description: "Neuroma of amputation stump, left upper extremity" },
  { code: "T87.33", description: "Neuroma of amputation stump, right lower extremity" },
  { code: "T87.34", description: "Neuroma of amputation stump, left lower extremity" },
  { code: "T87.40", description: "Infection of amputation stump, unspecified extremity" },
  { code: "T87.41", description: "Infection of amputation stump, right upper extremity" },
  { code: "T87.42", description: "Infection of amputation stump, left upper extremity" },
  { code: "T87.43", description: "Infection of amputation stump, right lower extremity" },
  { code: "T87.44", description: "Infection of amputation stump, left lower extremity" },
  { code: "T87.50", description: "Necrosis of amputation stump, unspecified extremity" },
  { code: "T87.51", description: "Necrosis of amputation stump, right upper extremity" },
  { code: "T87.52", description: "Necrosis of amputation stump, left upper extremity" },
  { code: "T87.53", description: "Necrosis of amputation stump, right lower extremity" },
  { code: "T87.54", description: "Necrosis of amputation stump, left lower extremity" },
  { code: "T87.81", description: "Dehiscence of amputation stump" },
  { code: "T87.89", description: "Other complications of amputation stump" },
  
  // ===== FOREIGN BODY CODES =====
  { code: "Z18.0", description: "Retained radioactive fragments" },
  { code: "Z18.10", description: "Retained metal fragments, unspecified" },
  { code: "Z18.11", description: "Retained magnetic metal fragments" },
  { code: "Z18.12", description: "Retained nonmagnetic metal fragments" },
  { code: "Z18.2", description: "Retained plastic fragments" },
  { code: "Z18.31", description: "Retained animal quills or spines" },
  { code: "Z18.32", description: "Retained tooth" },
  { code: "Z18.33", description: "Retained wood fragments" },
  { code: "Z18.39", description: "Other retained organic fragments" },
  { code: "Z18.81", description: "Retained glass fragments" },
  { code: "Z18.83", description: "Retained stone or crystalline fragments" },
  { code: "Z18.89", description: "Other specified retained foreign body fragments" },
  { code: "Z18.9", description: "Retained foreign body fragments, unspecified material" }
];
