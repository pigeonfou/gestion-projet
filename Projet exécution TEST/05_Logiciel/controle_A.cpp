// EXERCICE FICTIF LUMI-12 : outil PC de recette, aucune commande materielle.
#include <iostream>
#include <string>
#include <cmath>
int main(int argc, char** argv) {
  if (argc != 5) { std::cerr << "Usage: outil tension_V courant_mA led1(0|1) led2(0|1)\n"; return 2; }
  try {
    auto number=[](const char* s) { std::size_t n=0; double v=std::stod(s,&n); if(n!=std::string(s).size() || !std::isfinite(v)) throw 1; return v; };
    double volts=number(argv[1]), milliamps=number(argv[2]);
    auto bit=[](const char* s) { std::string v(s); if(v!="0" && v!="1") throw 1; return v=="1"; };
    bool led1=bit(argv[3]),led2=bit(argv[4]);
    bool ok = led1 && led2;
    std::cout << "VERSION FICTIVE A (regle incomplete volontaire) | " << (ok?"CONFORME":"NON_CONFORME") << "\n";
    return ok?0:1;
  } catch (...) { std::cerr << "Entree invalide\n"; return 2; }
}
