<?php
// Blockchain.php - คลาสสำหรับจัดการบล็อกเชน
require_once 'config.php';

class Blockchain {
    private $pdo;
    
    public function __construct($pdo) {
        $this->pdo = $pdo;
    }
    
    // สร้างบล็อกใหม่
    public function createBlock($data, $created_by) {
        $transactionStarted = false;
        
        try {
            // ตรวจสอบข้อมูลก่อนเริ่ม transaction
            if (empty($data) || empty($created_by)) {
                throw new Exception('ข้อมูลไม่ครบถ้วนสำหรับสร้างบล็อก');
            }
            
            $this->pdo->beginTransaction();
            $transactionStarted = true;
            
            // หาบล็อกล่าสุด
            $lastBlock = $this->getLastBlock();
            if (!$lastBlock) {
                throw new Exception('ไม่พบ Genesis Block');
            }
            
            $newIndex = $lastBlock['block_index'] + 1;
            $previousHash = $lastBlock['block_hash'];
            
            // สร้าง hash ของข้อมูล
            $dataHash = $this->createDataHash($data);
            
            // สร้าง Merkle root (simplified)
            $merkleRoot = $this->createMerkleRoot([$dataHash]);
            
            // สร้าง block hash
            $blockData = $newIndex . $previousHash . $dataHash . $merkleRoot . time();
            $blockHash = $this->mineBlock($blockData);
            
            // บันทึกบล็อกใหม่
            $stmt = $this->pdo->prepare("
                INSERT INTO blockchain_blocks 
                (block_index, previous_hash, data_hash, merkle_root, block_hash, created_by) 
                VALUES (?, ?, ?, ?, ?, ?)
            ");
            
            $stmt->execute([$newIndex, $previousHash, $dataHash, $merkleRoot, $blockHash, $created_by]);
            $blockId = $this->pdo->lastInsertId();
            
            $this->pdo->commit();
            
            return [
                'success' => true,
                'block_id' => $blockId,
                'block_hash' => $blockHash,
                'block_index' => $newIndex
            ];
            
        } catch (Exception $e) {
            if ($transactionStarted && $this->pdo->inTransaction()) {
                $this->pdo->rollBack();
            }
            return [
                'success' => false,
                'error' => $e->getMessage()
            ];
        }
    }
    
    // บันทึกข้อมูลการศึกษาลงบล็อกเชน
    public function storeEducationalRecord($recordData, $institutionId) {
        $transactionStarted = false;
        
        try {
            // ตรวจสอบข้อมูลก่อนเริ่ม transaction
            if (empty($recordData['student_id']) || empty($recordData['student_name']) || 
                empty($recordData['course_code']) || empty($recordData['course_name']) ||
                empty($recordData['grade']) || empty($recordData['credits']) ||
                empty($recordData['semester']) || empty($recordData['academic_year'])) {
                throw new Exception('ข้อมูลไม่ครบถ้วน');
            }
            
            $this->pdo->beginTransaction();
            $transactionStarted = true;
            
            // ตรวจสอบข้อมูลซ้ำ
            $stmt = $this->pdo->prepare("
                SELECT id FROM educational_records 
                WHERE student_id = ? AND course_code = ? AND semester = ? 
                AND academic_year = ? AND institution_id = ?
            ");
            $stmt->execute([
                $recordData['student_id'],
                $recordData['course_code'],
                $recordData['semester'],
                $recordData['academic_year'],
                $institutionId
            ]);
            
            if ($stmt->fetch()) {
                throw new Exception('พบข้อมูลซ้ำในระบบ');
            }
            
            // สร้าง hash ของข้อมูล
            $recordHash = $this->createRecordHash($recordData);
            
            // บันทึกข้อมูลการศึกษา
            $stmt = $this->pdo->prepare("
                INSERT INTO educational_records 
                (student_id, student_name, course_code, course_name, grade, credits, 
                 semester, academic_year, institution_id, record_hash) 
                VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?)
            ");
            
            $stmt->execute([
                $recordData['student_id'],
                $recordData['student_name'],
                $recordData['course_code'],
                $recordData['course_name'],
                $recordData['grade'],
                $recordData['credits'],
                $recordData['semester'],
                $recordData['academic_year'],
                $institutionId,
                $recordHash
            ]);
            
            $recordId = $this->pdo->lastInsertId();
            
            // สร้างธุรกรรม
            $transactionHash = $this->createTransactionHash([
                'type' => 'create_record',
                'record_id' => $recordId,
                'timestamp' => time(),
                'user_id' => $institutionId
            ]);
            
            $stmt = $this->pdo->prepare("
                INSERT INTO transactions 
                (from_user_id, transaction_type, data_id, transaction_hash, status) 
                VALUES (?, 'create_record', ?, ?, 'pending')
            ");
            
            $stmt->execute([$institutionId, $recordId, $transactionHash]);
            
            // สร้างบล็อกใหม่
            $blockResult = $this->createBlock([
                'type' => 'educational_record',
                'record_id' => $recordId,
                'record_hash' => $recordHash,
                'transaction_hash' => $transactionHash
            ], $institutionId);
            
            if ($blockResult['success']) {
                // อัปเดตข้อมูลการศึกษาด้วย block_id
                $stmt = $this->pdo->prepare("
                    UPDATE educational_records SET block_id = ?, verified_at = NOW() 
                    WHERE id = ?
                ");
                $stmt->execute([$blockResult['block_id'], $recordId]);
                
                // อัปเดตสถานะธุรกรรม
                $stmt = $this->pdo->prepare("
                    UPDATE transactions SET status = 'confirmed', block_id = ?, confirmed_at = NOW() 
                    WHERE transaction_hash = ?
                ");
                $stmt->execute([$blockResult['block_id'], $transactionHash]);
            }
            
            $this->pdo->commit();
            
            return [
                'success' => true,
                'record_id' => $recordId,
                'record_hash' => $recordHash,
                'transaction_hash' => $transactionHash,
                'block_hash' => $blockResult['block_hash'] ?? null
            ];
            
        } catch (Exception $e) {
            if ($transactionStarted && $this->pdo->inTransaction()) {
                $this->pdo->rollBack();
            }
            return [
                'success' => false,
                'error' => $e->getMessage()
            ];
        }
    }
    
    // ตรวจสอบความถูกต้องของข้อมูล
    public function verifyRecord($recordId) {
        try {
            // ดึงข้อมูลการศึกษา
            $stmt = $this->pdo->prepare("
                SELECT er.*, bb.block_hash, bb.block_index 
                FROM educational_records er 
                LEFT JOIN blockchain_blocks bb ON er.block_id = bb.id 
                WHERE er.id = ?
            ");
            $stmt->execute([$recordId]);
            $record = $stmt->fetch();
            
            if (!$record) {
                return ['success' => false, 'error' => 'ไม่พบข้อมูล'];
            }
            
            // ตรวจสอบ hash ของข้อมูล
            $calculatedHash = $this->createRecordHash([
                'student_id' => $record['student_id'],
                'student_name' => $record['student_name'],
                'course_code' => $record['course_code'],
                'course_name' => $record['course_name'],
                'grade' => $record['grade'],
                'credits' => $record['credits'],
                'semester' => $record['semester'],
                'academic_year' => $record['academic_year']
            ]);
            
            $isValid = ($calculatedHash === $record['record_hash']);
            
            // ตรวจสอบความเชื่อมโยงของบล็อกเชน
            $blockchainValid = $this->validateBlockchainIntegrity();
            
            return [
                'success' => true,
                'is_valid' => $isValid && $blockchainValid,
                'record_hash' => $record['record_hash'],
                'calculated_hash' => $calculatedHash,
                'block_hash' => $record['block_hash'],
                'block_index' => $record['block_index'],
                'blockchain_integrity' => $blockchainValid
            ];
            
        } catch (Exception $e) {
            return [
                'success' => false,
                'error' => $e->getMessage()
            ];
        }
    }
    
    // ตรวจสอบความสมบูรณ์ของบล็อกเชน
    public function validateBlockchainIntegrity() {
        try {
            $stmt = $this->pdo->prepare("
                SELECT * FROM blockchain_blocks 
                ORDER BY block_index ASC
            ");
            $stmt->execute();
            $blocks = $stmt->fetchAll();
            
            for ($i = 1; $i < count($blocks); $i++) {
                if ($blocks[$i]['previous_hash'] !== $blocks[$i-1]['block_hash']) {
                    return false;
                }
            }
            
            return true;
            
        } catch (Exception $e) {
            return false;
        }
    }
    
    // สร้าง hash ของข้อมูล
    private function createDataHash($data) {
        return hash('sha256', json_encode($data, JSON_UNESCAPED_UNICODE));
    }
    
    // สร้าง hash ของข้อมูลการศึกษา
    private function createRecordHash($recordData) {
        $dataString = implode('|', [
            $recordData['student_id'],
            $recordData['student_name'],
            $recordData['course_code'],
            $recordData['course_name'],
            $recordData['grade'],
            $recordData['credits'],
            $recordData['semester'],
            $recordData['academic_year']
        ]);
        
        return hash('sha256', $dataString);
    }
    
    // สร้าง hash ของธุรกรรม
    private function createTransactionHash($transactionData) {
        return hash('sha256', json_encode($transactionData, JSON_UNESCAPED_UNICODE));
    }
    
    // สร้าง Merkle root (simplified)
    private function createMerkleRoot($hashes) {
        if (count($hashes) === 1) {
            return $hashes[0];
        }
        
        $newHashes = [];
        for ($i = 0; $i < count($hashes); $i += 2) {
            $left = $hashes[$i];
            $right = isset($hashes[$i + 1]) ? $hashes[$i + 1] : $left;
            $newHashes[] = hash('sha256', $left . $right);
        }
        
        return $this->createMerkleRoot($newHashes);
    }
    
    // การขุดบล็อก (Proof of Work แบบง่าย)
    private function mineBlock($data, $difficulty = 2) {
        $target = str_repeat("0", $difficulty);
        $nonce = 0;
        
        while (true) {
            $hash = hash('sha256', $data . $nonce);
            if (substr($hash, 0, $difficulty) === $target) {
                return $hash;
            }
            $nonce++;
            
            // จำกัดการทำงานเพื่อป้องกันการใช้ทรัพยากรมากเกินไป
            if ($nonce > 100000) {
                break;
            }
        }
        
        return hash('sha256', $data . $nonce);
    }
    
    // หาบล็อกล่าสุด
    private function getLastBlock() {
        $stmt = $this->pdo->prepare("
            SELECT * FROM blockchain_blocks 
            ORDER BY block_index DESC 
            LIMIT 1
        ");
        $stmt->execute();
        
        $lastBlock = $stmt->fetch();
        
        // ถ้าไม่มีบล็อกเลย ให้สร้าง Genesis Block
        if (!$lastBlock) {
            $this->createGenesisBlock();
            
            // ลองหาบล็อกล่าสุดอีกครั้ง
            $stmt->execute();
            $lastBlock = $stmt->fetch();
        }
        
        return $lastBlock;
    }
    
    // สร้าง Genesis Block
    private function createGenesisBlock() {
        try {
            $stmt = $this->pdo->prepare("
                INSERT INTO blockchain_blocks 
                (block_index, previous_hash, data_hash, merkle_root, block_hash, created_by) 
                VALUES (0, '0000000000000000000000000000000000000000000000000000000000000000', 
                        'genesis_data_hash', 'genesis_merkle_root', 
                        '0000000000000000000000000000000000000000000000000000000000000001', 1)
            ");
            $stmt->execute();
            
            return true;
        } catch (Exception $e) {
            // Genesis Block อาจถูกสร้างแล้วโดย process อื่น
            return false;
        }
    }
    
    // ดึงข้อมูลบล็อกเชนทั้งหมด
    public function getAllBlocks() {
        $stmt = $this->pdo->prepare("
            SELECT bb.*, u.full_name as creator_name 
            FROM blockchain_blocks bb 
            LEFT JOIN users u ON bb.created_by = u.id 
            ORDER BY bb.block_index ASC
        ");
        $stmt->execute();
        
        return $stmt->fetchAll();
    }
    
    // ดึงข้อมูลการศึกษาทั้งหมด
    public function getAllEducationalRecords($institutionId = null) {
        $sql = "
            SELECT er.*, bb.block_hash, bb.block_index, u.full_name as institution_name 
            FROM educational_records er 
            LEFT JOIN blockchain_blocks bb ON er.block_id = bb.id 
            LEFT JOIN users u ON er.institution_id = u.id
        ";
        
        $params = [];
        if ($institutionId) {
            $sql .= " WHERE er.institution_id = ?";
            $params[] = $institutionId;
        }
        
        $sql .= " ORDER BY er.created_at DESC";
        
        $stmt = $this->pdo->prepare($sql);
        $stmt->execute($params);
        
        return $stmt->fetchAll();
    }
}
?>