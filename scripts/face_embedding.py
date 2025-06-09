#!/usr/bin/env python3
"""
얼굴 벡터 추출 스크립트
사용법: python3 face_embedding.py [이미지경로]
"""

import sys
import json
import os
from pathlib import Path
import traceback

# DeepFace 홈 디렉토리를 프로젝트 내부로 설정
project_dir = os.path.dirname(os.path.dirname(os.path.abspath(__file__)))
deepface_home = os.path.join(project_dir, '.deepface')
os.environ['DEEPFACE_HOME'] = deepface_home

# .deepface 디렉토리 생성 (권한 문제 해결)
try:
    os.makedirs(deepface_home, exist_ok=True)
    # 권한 확인을 위한 테스트 파일 생성
    test_file = os.path.join(deepface_home, 'test_write.tmp')
    with open(test_file, 'w') as f:
        f.write('test')
    os.remove(test_file)
except PermissionError:
    # 권한 문제가 있으면 임시 디렉토리 사용
    import tempfile
    deepface_home = os.path.join(tempfile.gettempdir(), 'deepface')
    os.environ['DEEPFACE_HOME'] = deepface_home
    os.makedirs(deepface_home, exist_ok=True)

# TensorFlow 로그 억제
os.environ['TF_CPP_MIN_LOG_LEVEL'] = '3'
import warnings
warnings.filterwarnings('ignore')

try:
    from deepface import DeepFace
except ImportError:
    print(json.dumps({"error": "DeepFace 라이브러리가 설치되지 않았습니다. pip install deepface 로 설치해주세요."}, ensure_ascii=False))
    sys.exit(1)
except Exception as e:
    print(json.dumps({"error": f"DeepFace 초기화 오류: {str(e)}"}, ensure_ascii=False))
    sys.exit(1)

def extract_face_embedding(img_path):
    """
    이미지에서 얼굴 벡터를 추출합니다.

    Args:
        img_path (str): 이미지 파일 경로

    Returns:
        dict: 성공 시 {"embedding": [벡터]} 반환, 실패 시 {"error": "에러메시지"} 반환
    """
    try:
        # 파일 존재 확인
        if not os.path.exists(img_path):
            return {"error": f"이미지 파일을 찾을 수 없습니다: {img_path}"}

        # 파일 크기 확인 (너무 큰 파일 방지)
        file_size = os.path.getsize(img_path)
        if file_size > 10 * 1024 * 1024:  # 10MB 제한
            return {"error": "이미지 파일이 너무 큽니다. 10MB 이하의 파일을 사용해주세요."}

        # 얼굴 벡터 추출
        result = DeepFace.represent(
            img_path=img_path, 
            model_name="Facenet512",  # 정확도와 성능의 균형
            enforce_detection=True,  # 얼굴이 감지되지 않으면 에러
            detector_backend='opencv'  # 얼굴 감지 백엔드
        )

        # result는 리스트 형태로 반환됨 (여러 얼굴이 있을 수 있음)
        if not result:
            return {"error": "얼굴을 감지할 수 없습니다."}

        # 첫 번째 얼굴의 벡터 사용
        embedding = result[0]["embedding"]

        # 벡터가 리스트인지 확인
        if not isinstance(embedding, list):
            embedding = embedding.tolist()

        return {"embedding": embedding}

    except ValueError as e:
        # 얼굴 감지 실패
        error_msg = str(e)
        if "Face could not be detected" in error_msg:
            return {"error": "얼굴을 감지할 수 없습니다. 명확한 얼굴이 포함된 이미지를 사용해주세요."}
        elif "non-english characters" in error_msg:
            return {"error": "파일 경로에 한글이나 특수문자가 포함되어 있습니다. 영문 파일명을 사용해주세요."}
        else:
            return {"error": f"얼굴 감지 오류: {error_msg}"}

    except ImportError as e:
        return {"error": f"필요한 라이브러리를 찾을 수 없습니다: {str(e)}"}

    except Exception as e:
        # 더 자세한 에러 정보 제공
        error_msg = str(e)
        tb = traceback.format_exc()
        return {"error": f"알 수 없는 오류: {error_msg}", "traceback": tb}

def main():
    """메인 함수"""
    try:
        if len(sys.argv) != 2:
            print(json.dumps({"error": "사용법: python3 face_embedding.py [이미지경로]"}, ensure_ascii=False))
            sys.exit(1)

        img_path = sys.argv[1]
        
        # 경로 정규화
        img_path = os.path.abspath(img_path)
        
        result = extract_face_embedding(img_path)

        # 결과를 JSON으로 출력 (한글 지원)
        print(json.dumps(result, ensure_ascii=False))

        # 에러가 있으면 exit code 1
        if "error" in result:
            sys.exit(1)
            
    except Exception as e:
        # 예상치 못한 에러 처리
        error_result = {
            "error": f"스크립트 실행 중 오류: {str(e)}",
            "traceback": traceback.format_exc()
        }
        print(json.dumps(error_result, ensure_ascii=False))
        sys.exit(1)

if __name__ == "__main__":
    main()